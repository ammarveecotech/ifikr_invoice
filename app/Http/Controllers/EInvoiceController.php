<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Doc;
use App\Models\DocBuyer;
use App\Models\DocDetail;
use App\Models\DocSupplier;
use App\Models\EInvoiceClient;
use App\Models\Setting;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class EInvoiceController extends Controller
{
    public function create(Request $request)
    {
        $company =  $request->company;
        if ($request->documents) {
            $createDocs = $this->createDoc($request);
            $client = new EInvoiceClient($company);
            $invoices = [];
            if (!empty($createDocs['error'])) {
                return new ApiResponse($createDocs['error'], "There is an error with one or more documents submitted", false, 422);
            }
            foreach ($createDocs['success'] as $key => $doc) {
                $invoices[] = Doc::find($doc)->first();
            }
            //$invoices[] = Doc::find(2);
            if (!empty($invoices)) {
                $response = $client->submitDoc($invoices);
                $responses = [];
                $message = "Request sent to LHDN successfully";
                if ($response['success']) {
                    $success = true;
                    foreach ($response['response']['acceptedDocuments'] as $acceptedDoc) {
                        if ($invoice = Doc::where('invoice_num', $acceptedDoc['invoiceCodeNumber'])->first()) {
                            $submissionUid = $response['response']['submissionUid'];
                            $invoice->submission_UID = $submissionUid;
                            $invoice->document_UID = $acceptedDoc['uuid'];
                            $invoice->submitted_at = Carbon::now();
                            $invoice->status = 'Submitted';
                            $invoice->save();
                            $invoice->updateSubmissions();
                            $acceptedDoc['submission_'] = $invoice->invoice_num;
                            $successful = [];
                            $successful['original_invoice_num'] = $invoice->original_invoice_num;
                            $data = ['submission_uid' => $submissionUid, 'document_uid' => $acceptedDoc['uuid'], 'internal_document_number' => $acceptedDoc['invoiceCodeNumber']];
                            $successful['data'] = $data;
                            $responses[] = $successful;
                        }
                    }
                    foreach ($response['response']['rejectedDocuments'] as $rejectedDoc) {
                        $success = false;

                        if ($invoice = Doc::where('invoice_num', $rejectedDoc['invoiceCodeNumber'])->first()) {
                            $extra = [
                                'dateTimeReceived' => $rejectDoc['dateTimeReceived'] ?? null,
                                'dateTimeSubmitted' => $rejectDoc['dateTimeSubmitted'] ?? null,
                                'documentStatusReason' => $rejectDoc['documentStatusReason'] ?? null,
                                'cancelDateTime' => $rejectDoc['cancelDateTime'] ?? null,
                                'validationResults' => $rejectDoc['validationResults'] ?? null,
                            ];
                            $invoice->extra = json_encode($extra);

                            $invoice->submitted_at = Carbon::now();
                            $invoice->status = 'Invalid';
                            $invoice->error_msg = 'Validation Error from LHDN';
                            $invoice->save();
                            $error = [];
                            $error['original_invoice_num'] = $invoice->original_invoice_num;
                            $error['data'] = $rejectedDoc['error']['details'];
                            $error['error'] = $rejectedDoc['error']['message'];
                            $responses[] = $error;
                        }
                        $message = 'Request sent to LHDN successfully, however there are some documents with error';
                    }
                    return new ApiResponse($responses, $message, $success, 201);
                }
                return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
            }
        }
        return new ApiResponse([], "Invalid request", false, 422);
    }

    public function getDoc(Request $request)
    {
        $rules = [
            'document_uid' => 'required',
        ];

        if ($error = $this->validateRules($rules, $request->all())) {
            return new ApiResponse([], $error, false, 422);
        }

        $document = Doc::where('document_UID', $request->document_uid)->first();

        if (!$document) {
            return new ApiResponse([], 'Document not found', false, 404);
        }

        $extra = json_decode($document->extra, true) ?? [];
        return new ApiResponse([
            'id' => $document->id,
            'internalId' => $document->invoice_num,
            'status' => $document->status,
            'uuid' => $document->document_UID,
            'submissionUid' => $document->submission_UID,
            'dateTimeValidated' => $document->validated_at ? $document->validated_at->format('Y-m-d\TH:i:s\Z') : null,
            'dateTimeReceived' => $extra['dateTimeReceived'] ?? null,
            'dateTimeSubmitted' => $extra['dateTimeSubmitted'] ?? null,
            'documentStatusReason' => $extra['documentStatusReason'] ?? null,
            'cancelDateTime' => $extra['cancelDateTime'] ?? null,
            'validationResults' => $extra['validationResults'] ?? null,
        ], 'Success', true, 202);
    }

    public function getDocRaw(Request $request)
    {
        $rules = [
            'document_uid' => 'required',
        ];

        if ($error = $this->validateRules($rules, $request->all())) {
            return new ApiResponse([], $error, false, 422);
        }

        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $response = $client->getDocumentDetail($request->document_uid);

        if ($response['success']) {
            return new ApiResponse($response['response'], 'Success', true, 202);
        }

        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    public function searchDoc(Request $request)
    {
        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $rules = [
            'uuid' => 'nullable|string',
            'submissionDateFrom' => 'nullable|date|date_format:Y-m-d\TH:i:s\Z|before_or_equal:submissionDateTo',
            'submissionDateTo' => 'nullable|date|date_format:Y-m-d\TH:i:s\Z|after_or_equal:submissionDateFrom',
            'continuationToken' => 'nullable|string',
            'pageSize' => 'nullable|integer|max:100',
            'issueDateFrom' => 'nullable|date|date_format:Y-m-d\TH:i:s\Z|before_or_equal:issueDateTo',
            'issueDateTo' => 'nullable|date|date_format:Y-m-d\TH:i:s\Z|after_or_equal:issueDateFrom',
            'direction' => 'nullable|string|in:Sent,Received',
            'status' => 'nullable|string|in:Valid,Invalid,Cancelled,Submitted',
            'documentType' => 'nullable|string|in:01,02,03,04,11,12,13,14',
            'receiverId' => 'nullable|string|required_if:direction,Sent',
            'receiverIdType' => 'nullable|string|required_if:direction,Sent|in:BRN,PASSPORT,NRIC,ARMY',
            'issuerTin' => 'nullable|string|required_if:direction,Received',
        ];
        $data = $request->except(['company']);
        if ($error = $this->validateRules($rules, $data)) {
            return new ApiResponse([], $error, false, 422);
        }
        $response = $client->searchDocument($data);
        if ($response['success']) {
            return new ApiResponse($response['response']['result'], 'Success', true, 202);
        }
        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    public function cancelDoc(Request $request)
    {
        $rules = [
            'document_uid' => 'required',
            'reason' => 'required',
        ];

        $data = $request->all();

        if ($error = $this->validateRules($rules, $data)) {
            return new ApiResponse([], $error, false, 422);
        }

        if (Route::currentRouteName() === 'e-invoice.cancel-doc') {
            $data['status'] = 'Cancelled';
        } else {
            $data['status'] = 'Rejected';
        }
        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $response = $client->rejectCancelDocument($data);

        if ($response['success']) {
            if ($doc = Doc::where('document_UID', $data['document_uid'])->first()) {
                $doc->status = $data['status'];
                $doc->save();
            }
            return new ApiResponse($response['response'], 'Success', true, 202);
        }

        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    public function rejectDoc(Request $request)
    {
        return $this->cancelDoc($request);
    }

    public function getSubmission(Request $request)
    {
        $rules = [
            'submission_uid' => 'required',
        ];

        if ($error = $this->validateRules($rules, $request->all())) {
            return new ApiResponse([], $error, false, 422);
        }

        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $response = $client->getSubmission($request->submission_uid);

        if ($response['success']) {
            return new ApiResponse($response['response'], 'Success', true, 202);
        }

        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    private function createDoc(Request $request)
    {
        $errorMessage = $success = [];
        $invoiceVersion = "1.1";
        $companyId = $request->company->id;
        $foreign = ['supplier', 'buyer', 'items'];
        $codeKey = ['invoice_type', 'currency', 'payment_mode', 'tax_type', 'state', 'country', 'registration_type', 'classification', 'measurement', 'msic'];
        foreach ($request->documents as $document) {
            if ($validated = $this->validateRequest($document)) {
                $error = [];
                $error['original_invoice_num'] = $document['original_invoice_num'] ?? '';
                foreach ($validated->toArray() as $field => $message) {
                    $error['data'][$field] = $message[0];
                }
                $error['error'] = "One or more fields is required";
                $errorMessage = $error;
                continue;
            }
            $supplier = $buyer = null;
            $items = [];
            try {
                DB::beginTransaction();
                $error = [];
                if ($doc = Doc::where('invoice_type', $this->getSettingByCategoryAndValue('InvoiceType', $document['invoice_type']))->where('original_invoice_num', $document['original_invoice_num'])->whereHas('supplier', function ($q) use ($document) {
                    $q->where('tin', $document['supplier']['tin']);
                })->first()) {
                    if ($doc->isSubmittedOrSuccess()) {
                        $error['original_invoice_num'] = $document['original_invoice_num'];
                        $error['data'] = ["submission_uid" => $doc->submission_UID, "document_uid" => $doc->document_UID, 'internal_document_number' => $doc->invoice_num];
                        $error['error'] = "This document is already submitted.";
                        $errorMessage[] = $error;
                        continue;
                    }
                    if ($doc->details) {
                        $doc->details()->delete();
                    }
                } else {
                    $doc = new Doc();
                }
                $doc->company_id = $companyId;
                $doc->einvoice_ver = $invoiceVersion;
                foreach ($document as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $key2 => $value2) {
                            if (in_array($key2, $codeKey)) {
                                if (!$valueExist = $this->getSettingByCategoryAndValue($key2, $value2)) {
                                    $categories = $this->getSettingsByCategory($key2);
                                    $messages = [
                                        "{$key}_$key2.in" => "The selected {$key}_$key2 is invalid. Available options are: " . implode(', ', $categories) . '.',
                                    ];
                                    $rules = [
                                        "{$key}_$key2" => "|in:" . implode(',', $categories),
                                    ];
                                    $error['original_invoice_num'] = $document['original_invoice_num'];
                                    $error['data'] = "{$key}_$key2";
                                    $error['error'] = $this->validateRules($rules, ["{$key}_$key2" => "$value2"], $messages)->first("{$key}_$key2");
                                    $errorMessage[] = $error;
                                    break;
                                } else {
                                    $value2 = $valueExist;
                                }
                            }
                            if (in_array($key, $foreign)) {
                                if ($key == 'supplier') {
                                    $supplier = $supplier ?? ($doc->supplier ?? new DocSupplier());
                                    $target = $supplier;
                                } else if ($key == 'buyer') {
                                    $buyer = $buyer ?? ($doc->buyer ?? new DocBuyer());
                                    $target = $buyer;
                                } else if ($key == 'items') {
                                    $target = null;
                                    $item = new DocDetail();
                                    foreach ($value2 as $key3 => $value3) {
                                        if (in_array($key3, $codeKey)) {
                                            if (!$valueExist = $this->getSettingByCategoryAndValue($key3, $value3)) {
                                                $categories = $this->getSettingsByCategory($key3);
                                                $messages = [
                                                    "{$key}_$key3.in" => "The selected {$key}_$key3 is invalid. Available options are: " . implode(', ', $categories) . '.',
                                                ];
                                                $rules = [
                                                    "{$key}_$key3" => "|in:" . implode(',', $categories),
                                                ];
                                                $error['original_invoice_num'] = $document['original_invoice_num'];
                                                $error['data'] = "{$key}_$key3";
                                                $error['error'] = $this->validateRules($rules, ["{$key}_$key3" => "$value3"], $messages)->first("{$key}_$key3");
                                                $errorMessage[] = $error;
                                                break;
                                            } else {
                                                $value3 = $valueExist;
                                            }
                                        }
                                        $item->{"$key3"} = $value3;
                                    }
                                    $items[] = $item;
                                }
                                if ($target) {
                                    $target->$key2 = $value2;
                                }
                            } else {
                                $thisKey = $key;
                                if ($thisKey == 'shipping') {
                                    $thisKey = 'shipping_recipient';
                                }
                                $doc->{"{$thisKey}_$key2"} = $value2;
                            }
                        }
                    } else {
                        if (in_array($key, $codeKey)) {
                            if (!$valueExist = $this->getSettingByCategoryAndValue($key, $value)) {
                                if ($key == "invoice_type" || $key == 'tax_type') {
                                    if (strlen($value) == 1) {
                                        if ($key == 'tax_type' && $value == "E") {
                                        } else {
                                            $value = '12345';
                                        }
                                    }
                                }
                                $categories = $this->getSettingsByCategory($key);
                                $messages = [
                                    "$key.in" => "The selected $key is invalid. Available options are: " . implode(', ', $categories) . '.',
                                ];
                                $rules = [
                                    "$key" => "|in:" . implode(',', $categories),
                                ];
                                $error['original_invoice_num'] = $document['original_invoice_num'];
                                $error['data'] = "$key";
                                $error['error'] = $this->validateRules($rules, ["$key" => "$value"], $messages)->first("$key");
                                $errorMessage[] = $error;
                                break;
                            } else {
                                $value = $valueExist;
                            }
                        }
                        $doc->{$key} = $value;
                    }
                }

                // if ($supplier && $buyer) {
                    // $tinValidation = ['supplier', 'buyer'];
                    // foreach ($tinValidation as $tinOwner) {
                    //     // Only validate if TIN does not start with EI000000000, those are general tins and cannot be validated
                    //     if (strpos($$tinOwner->tin, 'EI000000000') !== 0) {
                    //         $validateTinRequest = new Request();
                    //         $validateTinRequest->merge([
                    //             'company' => $request->company,
                    //             'tin_number' => $$tinOwner->tin,
                    //             'authentication_type' => 'BRN',
                    //             'authentication_value' => $$tinOwner->registration_num
                    //         ]);
                    //         $validateSupplier = $this->validateTin($validateTinRequest, true);
                    //         if (!$validateSupplier['success']) {
                    //             $error['original_invoice_num'] = $document['original_invoice_num'];
                    //             $error['data'] = "{$tinOwner}.registration_num";
                    //             $error['error'] = "BRN for $tinOwner is invalid. If your company is registered using old BRN number, please use it instead.";
                    //             $errorMessage[] = $error;
                    //         }
                    //     }
                    // }
                    // if (!in_array($doc->invoice_type, ["01", "11"])) {
                    //     $originalInvoice = $doc->getOriginalInvoice($supplier->tin);
                    //     if (!$originalInvoice || $originalInvoice->status != 'Valid') {
                    //         $error['original_invoice_num'] = $document['original_invoice_num'];
                    //         $error['data'] = "original_invoice_num";
                    //         $error['error'] = "Invoice does not exist or not valid. Please create the invoice first and ensure that it's submitted and valid.";
                    //         $errorMessage[] = $error;
                    //     } else if ($originalInvoice->invoice_datetime >= $doc->invoice_datetime) {
                    //         $error['original_invoice_num'] = $document['original_invoice_num'];
                    //         $error['data'] = "invoice_datetime";
                    //         $note = Setting::find($this->getSettingByCategoryAndValue('InvoiceType', $doc->invoice_type))->name;
                    //         $error['error'] = "$note issuance date time must be after Invoice.";
                    //         $errorMessage[] = $error;
                    //     }
                    // }
                // }

                if (!empty($error)) {
                    continue;
                }

                if (!$doc->invoice_num) {
                    $doc->generateInvoiceNumber();
                }
                $doc->save();
                $supplier->doc_id = $doc->id;
                $supplier->save();
                $buyer->doc_id = $doc->id;
                $buyer->save();

                foreach ($items as $item) {
                    $item->doc_id =  $doc->id;
                    $item->save();
                }
                DB::commit();
                $success[] = ['doc_id' => $doc->id];
            } catch (Exception $e) {
                DB::rollBack();
                $error = ['original_invoice_num' => $document['original_invoice_num'] ?? "", 'data' => [], 'error' => 'Internal Server error'];
                if ($e->getCode() == '42S22') {
                    $error['error'] = 'One or more field provided is invalid';
                    $errMsg = $e->getMessage();

                    if (preg_match("/Unknown column '(.*?)' in 'field list'/", $errMsg, $matches)) {
                        $error['data'] = $matches[1];
                    }
                } else if ($e->getCode() == '22001') {
                    $error['error'] = 'Data too long for one or more fields';

                    $errMsg = $e->getMessage();
                    if (preg_match("/Data too long for column '(.*?)' at row \d+/", $errMsg, $matches)) {
                        $error['data'] = $matches[1];
                    }
                } else if ($e->getCode() == '22007') {
                    $column = $type = '';
                    $patterns = [
                        '/Incorrect (.+?) value: \'[^\']*\' for column `[^`]*`\.`[^`]*`\.`([^`]*)`/' => ['type', 'column'],
                        '/Invalid (.+?) format: (.+?) for column `([^`]*)`/' => ['type', 'column'],
                    ];
                    $errMsg = $e->getMessage();
                    foreach ($patterns as $pattern => $matches) {
                        if (preg_match($pattern, $errMsg, $match)) {
                            $type = $match[array_search('type', $matches) + 1];
                            $column = $match[array_search('column', $matches) + 1];
                            break;
                        }
                    }
                    $error['error'] = 'Data type is invalid for one or more fields';
                    $error['data'] = [
                        'type' => $type,
                        'field' => $column,
                    ];
                } else {
                    $error['data'] = $e->getMessage();
                }
                $errorMessage[] = $error;
                continue;
            }
        }
        return ['success' => $success, 'error' => $errorMessage];
    }

    private function getSettingByCategoryAndValue($category, $value)
    {
        return ($setting = Setting::where('category', snakeToPascal($category))->where('value', $value)->first()) ? $setting->id : null;
    }

    private function getSettingsByCategory($category)
    {
        return ($setting = Setting::where('category', snakeToPascal($category))->pluck('value')->toArray()) ?: [];
    }

    public function validateTin(Request $request, $returnResponseOnly = false)
    {
        $availableOptions = ['BRN', 'PASSPORT', 'NRIC', 'ARMY'];

        $rules = [
            'tin_number' => 'required',
            'authentication_type' => 'required|in:' . implode(',', $availableOptions),
            'authentication_value' => 'required',
        ];

        $messages = [
            'authentication_type.in' => 'The selected authentication type is invalid. Available options are: ' . implode(', ', $availableOptions) . '.',
        ];

        if ($error = $this->validateRules($rules, $request->all(), $messages)) {
            return new ApiResponse([], $error, false, 422);
        }

        $data = ['idType' => $request->authentication_type, 'idValue' => $request->authentication_value];

        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $response = $client->validateTin($request->tin_number, $data);

        if ($returnResponseOnly) {
            return $response;
        }

        if ($response['success']) {
            return new ApiResponse([], 'Valid Tin Number', true, 202);
        }

        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    public function generateQR(Request $request)
    {
        $rules = [
            'document_uid' => 'required',
        ];

        if ($error = $this->validateRules($rules, $request->all())) {
            return new ApiResponse([], $error, false, 422);
        }

        $company =  $request->company;
        $client = new EInvoiceClient($company);
        $response = $client->getDocumentDetail($request->document_uid);

        if ($response['success']) {
            if ($response['response']['status'] != 'Valid') {
                return new ApiResponse($response['response'], "This document status is not valid.", false, 422);
            }

            $url = $client->getQRCodeURL($response['response']);
            $qr = generateQRCode($url);

            return $qr;
        }

        return new ApiResponse($response['response']['data'], $response['response']['error'], false, 422);
    }

    public function getCodeSetting($code)
    {
        $code = strtoupper($code);
        if (!in_array($code, ['STATE', 'COUNTRY', 'MSIC', 'CLASSIFICATION', 'MEASUREMENT'])) {
            return new ApiResponse([], "Code is not valid", false, 422);
        }

        $settings = Setting::where('category', $code)->pluck('value', 'name');

        return new ApiResponse($settings, 'Success', true, 202);
    }

    public function validateRules($rules, $request, $messages = [])
    {
        $validator = Validator::make($request, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors();
        }
    }

    private function validateRequest($document)
    {
        $required = ['original_invoice_num', 'invoice_type', 'invoice_datetime', 'currency', 'items', 'buyer', 'supplier', 'total_excl_tax', 'total_incl_tax', 'total_payable', 'total_tax_amt', 'total_tax_amt_per_tax_type', 'tax_type'];
        $requiredDetails = ['items.*.classification', 'items.*.description', 'items.*.unit_price', 'items.*.tax_type', 'items.*.tax_amount', 'items.*.subtotal', 'items.*.total_excl_tax'];
        $requiredSupplier = ['supplier.name', 'supplier.tin', 'supplier.registration_type', 'supplier.registration_num', 'supplier.msic', 'supplier.business_desc', 'supplier.contact_num', 'supplier.address1', 'supplier.state', 'supplier.country'];
        $requiredBuyer = ['buyer.name', 'buyer.tin', 'buyer.registration_type', 'buyer.registration_num', 'buyer.contact_num', 'buyer.address1', 'buyer.state', 'buyer.country'];
        $requiredList = ['required', 'requiredDetails', 'requiredSupplier', 'requiredBuyer'];

        $phoneRules = [
            'supplier.contact_num' => ['required', 'regex:/^\+[0-9]{1,15}$/'],
            'buyer.contact_num' => ['required', 'regex:/^\+[0-9]{1,15}$/'],
        ];

        $phoneMessages = [
            'supplier.contact_num.regex' => 'Supplier contact number must follow ITU E.164 standard (e.g. +601234567890).',
            'buyer.contact_num.regex' => 'Buyer contact number must follow ITU E.164 standard (e.g. +601234567890)',
        ];

        $emailRules = [];
        $emailMessages = [];
        if (isset($document['supplier']['email']) && $document['supplier']['email']) {
            $emailRules['supplier.email'] = ['email'];
            $emailMessages['supplier.email.email'] = 'Supplier email must be a valid email address.';
        }
        if (isset($document['buyer']['email']) && $document['buyer']['email']) {
            $emailRules['buyer.email'] = ['email'];
            $emailMessages['buyer.email.email'] = 'Buyer email must be a valid email address.';
        }

        if (!empty($emailRules)) {
            if ($error = $this->validateRules($emailRules, $document, $emailMessages)) {
                return $error;
            }
        }

        if ($error = $this->validateRules($phoneRules, $document, $phoneMessages)) {
            return $error;
        }

        foreach ($requiredList as $target) {
            $rules = [];
            foreach ($$target as $field) {
                $rules[$field] = 'required';
            }

            if ($error = $this->validateRules($rules, $document)) {
                return $error;
            }
        }
    }
}
