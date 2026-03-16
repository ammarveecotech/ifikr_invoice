<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\ArrayToXml\ArrayToXml;

class EInvoiceClient extends Model
{
    use HasFactory;

    private $clientId, $clientSecret, $url, $bearerToken, $companyId, $portalUrl;
    private $scope = 'InvoicingAPI';
    private $grant_type = 'client_credentials';

    const URL_PARAM = 'url_params';
    const URL = 'url';
    const FORM = 'form_params';
    const JSON = 'json';
    const INTERNAL_ERROR = 'Internal system error, please try again later';
    const LHDN_ERROR = 'Request failed from LHDN';

    public function __construct($company = null)
    {
        parent::__construct();

        if(!$company){
            return;
        }
        $this->company = $company;
        $this->clientId = ENV('EINVOCIE_CLIENT_ID');
        $this->clientSecret = ENV('EINVOICE_CLIENT_SECRET');
        $this->url = ENV('APP_ENV') == 'production' ? ENV('EINVOICE_PROD_URL') : ENV('EINVOICE_STAGING_URL');
        $this->portalUrl = ENV('APP_ENV') == 'production' ? ENV('EINVOICE_PROD_PORTAL_URL') : ENV('EINVOICE_STAGING_PORTAL_URL');

        if ($company->irb_token == null || $company->irb_expiration <= Carbon::now()) {
            $company->irb_token = $this->getBearerToken($company);
            if ($company->irb_token != null) {
                //Actual expiration is 1 hour, but we set it to 50 minutes to avoid issues
                $company->irb_expiration = Carbon::now()->addMinutes(50);
                $company->save();
            }
        }

        $this->bearerToken = $company->irb_token;
    }

    private function getBearerToken($company)
    {
        try {
            $data = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => $this->grant_type,
                'scope' => $this->scope
            ];

            $uri = '/connect/token';

            $onBehalf = $this->company->id == 1 ? [] : [
                'onbehalfof' => $this->company->tin,
            ];

            $response = Http::timeout(120)->asForm()
                ->withHeaders($onBehalf)->POST(
                    $this->url . $uri,
                    $data
                );

          	\Illuminate\Support\Facades\Log::debug($response);
            $this->logResponse($response, $data, $uri, null, true);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }
            return null;
        } catch (\Exception $e) {
            $this->logResponse($response ?? null, $data, $uri, $e->getMessage(), true);
            return null;
        }
    }

    private function request($uri, $method, $data = [], $dataType = 'json')
    {
        if ($this->bearerToken) {
            try {
                $request = Http::withOptions([
                    'connect_timeout' => 120,
                    'timeout' => 120,
                ])->withHeaders([
                    'Authorization' => 'Bearer ' . $this->bearerToken,
                ]);


                switch ($dataType) {
                    case 'form_params':
                        $response = $request->{$method}(
                            $this->url . $uri,
                            ['form_params' => $data]
                        );
                        break;
                    case 'url_params':
                        $url = $this->url . $uri . '?' . http_build_query($data);
                        $response = $request->{$method}($url);
                        break;
                    case 'url':
                        $url = $this->url . $uri;
                        $response = $request->{$method}($url, $data);
                        break;
                    case 'json':
                    default:
                        $response = $request->{$method}(
                            $this->url . $uri,
                            $data
                        );
                        break;
                }

                $this->logResponse($response, $data, $uri);
                if ($response->successful()) {
                    return ['success' => true, 'response' => $response->json()];
                }
                return ['success' => false, 'response' =>  ['error' => self::LHDN_ERROR, 'data' => $response->json()]];
            } catch (\Exception $e) {
                $this->logResponse($response ?? null, $data, $uri, $e->getMessage());
                return ['success' => false, 'response' => ['error' => self::INTERNAL_ERROR, 'data' => $e->getMessage()]];
            }
        }
        return ['success' => false, 'response' => ['error' => 'Bearer token not found', 'data' => []]];
    }

    public function submitDoc($invoices)
    {
        $uri = '/api/v1.0/documentsubmissions';

        $root = [
            'rootElementName' => 'Invoice',
            '_attributes' => [
                'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2'
            ]
        ];

        $documents = [];

        foreach ($invoices as $invoice) {
            $xml = ArrayToXml::convert(self::generateUbl($invoice), $root, true, "UTF-8");
            $xml = trim(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml));
          	\Illuminate\Support\Facades\Log::debug($xml);
            $docDigest = $this->docDigest($xml);
            $sig = signHash(canonicalize($xml));

            $certDigest = hashCertificate();
            $signature = addSignedProperties($certDigest, $sig, $docDigest);

            $xml = trim(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml));
            $xml = trim(str_replace(
                '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">',
                $signature,
                $xml
            ));
            $xml = str_replace('<cac:AccountingSupplierParty>', '<cac:Signature><cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID><cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod></cac:Signature><cac:AccountingSupplierParty>', $xml);

            $b64Encoded = base64_encode($xml);
            $hash = hash('sha256', $xml);
            $invoiceNum = $invoice->invoice_num;
            $documents[] = [
                'format' => "XML",
                'documentHash' => $hash,
                'codeNumber' => $invoiceNum,
                'document' => $b64Encoded,
            ];
        }


        $data = [
            'documents' => $documents
        ];

        return $this->request($uri, 'POST', $data, self::JSON);
    }

    private function docDigest($content)
    {
        $canonicalizedXml = canonicalize($content);
        $hash =  hash('sha256', $canonicalizedXml);
        $bin =  $hash;
        $b64 = base64_encode(hex2bin($bin));
        return $b64;
    }

    public function validateTin($tin, $data)
    {
        $uri = "/api/v1.0/taxpayer/validate/$tin";
        return $this->request($uri, 'GET', $data, self::URL_PARAM);
    }

    private function logResponse($response, $data, $uri, $error = null, $encrypt = false)
    {
        $logger = new Logger();
        $logger->api_name = $uri;
        $logger->company_id = $this->company->id;
        $logger->request = $encrypt ? Crypt::encryptString(json_encode($data)) : json_encode($data);
        $logger->response = $error ? $error : ($encrypt ? Crypt::encryptString(json_encode($response->json())) : json_encode($response->json()));
        $logger->attribute_id = 1;
        $logger->status_code = $error ? 500 : $response->status();
        $logger->save();
    }

    private function generateUbl($invoice)
    {
        $document = [
            'cbc:ID' => $invoice->invoice_num,
            'cbc:IssueDate' => $invoice->invoice_datetime->utc()->format('Y-m-d'),
            'cbc:IssueTime' => $invoice->invoice_datetime->utc()->format('H:i:s\Z'),
            'cbc:InvoiceTypeCode' => [
                '_attributes' => [
                    'listVersionID' => $invoice->einvoice_ver,
                ],
                '_value' => "$invoice->invoice_type"
            ],
            'cbc:DocumentCurrencyCode' => $invoice->currency,
        ];

        if ($invoice->currency != 'MYR') {
            if (in_array($invoice->invoice_type, [11, 12, 13, 14])) {
                $document['cbc:TaxCurrencyCode'] = $invoice->currency;
            } else {
                $document['cbc:TaxCurrencyCode'] = 'MYR';
            }
        }

        if ($invoice->billing_startdate && $invoice->billing_enddate) {
            $document['cac:InvoicePeriod'] = [ //optional
                'cbc:StartDate' => $invoice->billing_startdate->utc()->format('Y-m-d'),
                'cbc:EndDate' => $invoice->billing_enddate->utc()->format('Y-m-d'),
                'cbc:Description' => $invoice->frequency,
            ];
        }

        if (in_array($invoice->invoice_type, [2, 3, 4, 12, 13, 14])) {
            $original_invoice = $invoice->getOriginalInvoice($invoice->supplier->tin);
            $document = $document + [
                'cac:BillingReference' => [ //optional
                    'cac:InvoiceDocumentReference' =>
                    [
                        'cbc:ID' => $original_invoice ? $original_invoice->invoice_num : 'NA',
                        'cbc:UUID' => $original_invoice ? $original_invoice->document_UID : 'NA',
                    ],
                ]
            ];
        } else {
            $document = $document + [
                'cac:BillingReference' => [ //optional
                    'cac:AdditionalDocumentReference' =>
                    [
                        'cbc:ID' => $invoice->original_invoice_num,
                    ],
                ]
            ];
        }

        $additionalDocumentReference = [];
        $additionalDocumentReference[] = [
            'cbc:ID' => $invoice->original_invoice_num,
            'cbc:DocumentType' => "CustomsImportForm",
        ];

        $additionalDocumentReference[] = [ //Reference Number of Customs Form No.2
            'cbc:ID' => $invoice->original_invoice_num,
            'cbc:DocumentType' => "K2",
        ];

        if ($invoice->fta) {
            $additionalDocumentReference[] = [ //FTA only
                'cbc:ID' => $invoice->fta,
                'cbc:DocumentType' => "FreeTradeAgreement",
                'cbc:DocumentDescription' => "FreeTradeAgreement",
            ];
        }

        if ($invoice->incoterms) {
            $additionalDocumentReference[] = [ //Incoterms
                'cbc:ID' => $invoice->incoterms,
            ];
        }

        if (!empty($additionalDocumentReference)) {
            $document['cac:AdditionalDocumentReference'] = $additionalDocumentReference;
        }

        // $document = $document + ['cac:Signature' => [
        //     'cbc:ID' =>'urn:oasis:names:specification:ubl:signature:Invoice',
        //     'cbc:SignatureMethod' => 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'
        // ]];

        $contact = [
            'cbc:Telephone' => $invoice->supplier->contact_num,
        ];
        if (!empty($invoice->supplier->email) && $invoice->supplier->email != null) {
            $contact['cbc:ElectronicMail'] = $invoice->supplier->email;
        }

        $buyerContact = [
            'cbc:Telephone' => $invoice->buyer->contact_num,
        ];
        if (!empty($invoice->buyer->email) && $invoice->buyer->email != null) {
            $buyerContact['cbc:ElectronicMail'] = $invoice->buyer->email;
        }

        $document = $document + [
            'cac:AccountingSupplierParty'  => [
                'cac:Party' => [
                    'cbc:IndustryClassificationCode' => [
                        '_attributes' => [
                            'name' => $invoice->supplier->business_desc
                        ],
                        '_value' => "{$invoice->supplier->msic}"
                    ],
                    'cac:PartyIdentification' => [
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => 'TIN'
                                ],
                                '_value' => "{$invoice->supplier->tin}"
                            ]
                        ],
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => "{$invoice->supplier->registration_type}"
                                ],
                                '_value' => "{$invoice->supplier->registration_num}"
                            ]
                        ]
                    ],
                    'cac:PostalAddress' => $this->fullAddress('address', $invoice->supplier),
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoice->supplier->name,
                    ],
                    'cac:Contact' => $contact,
                ]
            ],
            'cac:AccountingCustomerParty'  => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => 'TIN'
                                ],
                                '_value' => "{$invoice->buyer->tin}"
                            ]
                        ],
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => "{$invoice->buyer->registration_type}"
                                ],
                                '_value' => "{$invoice->buyer->registration_num}"
                            ]
                        ]
                    ],
                    'cac:PostalAddress' => $this->fullAddress('address', $invoice->buyer),
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoice->buyer->name,
                    ],
                    'cac:Contact' => $buyerContact,
                ]
            ]
        ];

        if ($invoice->exporter_authorisation_num) {
            $additionalAccounId = [
                'cbc:AdditionalAccountID' => [ //Authorisation Number for Certified Exporter (e.g., ATIGA number)
                    "_attributes" => [
                        "schemeAgencyName" => "CertEX"
                    ],
                    "_value" => "$invoice->exporter_authorisation_num",
                ]
            ];

            $document['cac:AccountingSupplierParty'] = $additionalAccounId + $document['cac:AccountingSupplierParty'];
        }

        if ($invoice->shipping_recipient_name) {
            $document['cac:Delivery']  = [ //shipping
                'cac:DeliveryParty' => [
                    'cac:PartyIdentification' => [
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => 'TIN'
                                ],
                                '_value' => "{$invoice->shipping_recipient_tin}"
                            ]
                        ],
                        [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => "{$invoice->shipping_recipient_registration_type}"
                                ],
                                '_value' => "{$invoice->shipping_recipient_registration_num}"
                            ]
                        ],
                    ],

                    'cac:PostalAddress' => $this->fullAddress('shipping_recipient_address', $invoice),
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoice->shipping_recipient_name,
                    ],
                ],
                'cac:Shipment' => [
                    'cbc:ID' => $invoice->original_invoice_num,
                    'cac:FreightAllowanceCharge' => [
                        'cbc:ChargeIndicator' => 'true',
                        'cbc:AllowanceChargeReason' => $invoice->other_charges_detail,
                        'cbc:Amount' => [
                            '_attributes' => [
                                'currencyID' => $invoice->currency,
                            ],
                            '_value' => "$invoice->other_charges_amount"
                        ],
                    ]
                ],
            ];
        }

        if ($invoice->payment_mode) {
            $document['cac:PaymentMeans'] = [
                'cbc:PaymentMeansCode' => $invoice->payment_mode,
            ];
            if ($invoice->supplier_bank_account_numb) {
                $document['cac:PaymentMeans']['cbc:PayeeFinancialAccount'] = [
                    'cbc:ID' => $invoice->supplier_bank_account_num
                ];
            }
        }
        if ($invoice->payment_terms) {
            $document['cac:PaymentTerms'] =  [
                'cbc:Note' => $invoice->payment_terms
            ];
        }

        if ($invoice->prepayment_datetime) {
            $document['cac:PrepaidPayment'] = [
                'cbc:ID' => $invoice->original_invoice_num,
                'cbc:PaidAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->prepayment_amount"
                ],
                'cbc:PaidDate' => $invoice->prepayment_datetime->format('Y-m-d'),
                'cbc:PaidTime' => $invoice->prepayment_datetime->format('H:i:s\Z'),
            ];
        }

        if ($invoice->additional_disc) {
            $document['cac:AllowanceCharge'] = [
                [
                    'cbc:ChargeIndicator' => 'false', //Discount
                    'cbc:AllowanceChargeReason' => $invoice->additional_disc_reason,
                    'cbc:Amount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$invoice->additional_disc"
                    ]
                ],
            ];

            if ($invoice->additional_fee) {
                $document['cac:AllowanceCharge'][] =
                    [
                        'cbc:ChargeIndicator' => 'true', //invoice additional charge
                        'cbc:AllowanceChargeReason' => $invoice->additional_fee_reason,
                        'cbc:Amount' => [
                            '_attributes' => [
                                'currencyID' => $invoice->currency,
                            ],
                            '_value' => "$invoice->additional_fee"
                        ]
                    ];
            }
        }

        if ($invoice->currency != 'MYR') {
        	$document = $document + [
                'cac:TaxExchangeRate' => [
                  	'cbc:SourceCurrencyCode' => $invoice->currency,
                  	'cbc:TargetCurrencyCode' => 'MYR',
                  	'cbc:CalculationRate' => $invoice->exchange_rate,
                ]
          	];
        }

        $document = $document + [
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency != 'MYR' && !in_array($invoice->invoice_type, [11, 12, 13, 14]) ? 'MYR' : $invoice->currency,
                    ],
                    '_value' => "$invoice->total_tax_amt"
                ],
                'cac:TaxSubtotal' => [
                    'cbc:TaxableAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$invoice->total_taxable_amt"
                    ],
                    'cbc:TaxAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency != 'MYR' && !in_array($invoice->invoice_type, [11, 12, 13, 14]) ? 'MYR' : $invoice->currency,
                        ],
                        '_value' => "$invoice->total_tax_amt_per_tax_type"
                    ],
                    // 'cbc:Percent' => 10,
                    // 'cbc:BaseUnitMeasure' => [
                    //     '_attributes' => [
                    //         'unitCode' => $invoice->currency,
                    //     ],
                    //     '_value' => "$invoice->total_tax_amt_per_tax_type"
                    // ],
                    'cbc:PerUnitAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$invoice->total_tax_amt_per_tax_type"
                    ],
                    'cac:TaxCategory' => [
                        'cbc:ID' => $invoice->tax_type,
                        'cac:TaxScheme' => [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => 'UN/ECE 5153',
                                    'schemeAgencyID' => '6',
                                ],
                                '_value' => "OTH"
                            ],
                        ],
                    ]
                ],
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:LineExtensionAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_nett_amt"
                ],
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_excl_tax"
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_incl_tax"
                ],
                'cbc:AllowanceTotalAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_dsc_val"
                ],
                'cbc:ChargeTotalAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_charge_amt"
                ],
                'cbc:PayableRoundingAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->rounding_amt"
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$invoice->total_payable"
                ],
            ]
        ];
        $invoiceDetails = [];
        foreach ($invoice->details as $key => $detail) {
            $iDetail = [
                'cbc:ID' => sprintf('%02d', $key + 1),
                'cbc:LineExtensionAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency,
                    ],
                    '_value' => "$detail->total_excl_tax"
                ],
            ];

            if ($detail->measurement) {
                $measurementArray = [
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => [
                            'unitCode' => $detail->measurement,
                        ],
                        '_value' => "$detail->qty"
                    ]
                ];

                $iDetail = array_slice($iDetail, 0, 1, true) +
                    $measurementArray +
                    array_slice($iDetail, 1, null, true);
            }

            if ($detail->discount_amt && $detail->discount_amt > 0) {
                $iDetail['cac:AllowanceCharge'] = [
                    [
                        'cbc:ChargeIndicator' => 'false', //Discount
                        'cbc:AllowanceChargeReason' => $detail->discount_reason,
                        'cbc:MultiplierFactorNumeric' => $detail->discount_rate,
                        'cbc:Amount' => [
                            '_attributes' => [
                                'currencyID' => $invoice->currency,
                            ],
                            '_value' => "$detail->discount_amt"
                        ]
                    ],
                ];

                if ($detail->charge_amt && $detail->charge_amt > 0) {
                    $iDetail['cac:AllowanceCharge'][] =
                        [
                            'cbc:ChargeIndicator' => 'true', //invoice additional charge
                            'cbc:AllowanceChargeReason' => $detail->charge_reason,
                            'cbc:MultiplierFactorNumeric' => $detail->charge_rate,
                            'cbc:Amount' => [
                                '_attributes' => [
                                    'currencyID' => $invoice->currency,
                                ],
                                '_value' => "$detail->charge_amt"
                            ]
                        ];
                }
            }
            $itemTax = [
                'cbc:TaxAmount' => [
                    '_attributes' => [
                        'currencyID' => $invoice->currency != 'MYR' && !in_array($invoice->invoice_type, [11, 12, 13, 14]) ? 'MYR' : $invoice->currency,
                    ],
                    '_value' => "$detail->tax_amount"
                ],
                'cac:TaxSubtotal' => [
                    'cbc:TaxableAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$detail->tax_exemption_amt"
                    ],
                    'cbc:TaxAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency != 'MYR' && !in_array($invoice->invoice_type, [11, 12, 13, 14]) ? 'MYR' : $invoice->currency,
                        ],
                        '_value' => "$detail->tax_amount"
                    ],
                    'cac:TaxCategory' => [
                        'cbc:ID' => $detail->tax_type,
                        'cac:TaxScheme' => [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeID' => 'UN/ECE 5153',
                                    'schemeAgencyID' => '6',
                                ],
                                '_value' => "OTH"
                            ],
                        ],
                    ],
                ],
            ];

            if ($detail->tax_type == 'E') {
                $itemTax['cac:TaxSubtotal']['cac:TaxCategory'] = [
                    'cbc:Percent' => $detail->tax_rate ?? 0,
                    'cbc:TaxExemptionReason' => $detail->tax_exemption_details
                ];
            }

            $iDetail['cac:TaxTotal'] = $itemTax;
            $iDetail = $iDetail + [
                'cac:Item' => [
                    'cbc:Description' => $detail->description,
                    'cac:CommodityClassification' => [
                        [
                            [
                                'cbc:ItemClassificationCode' => [
                                    '_attributes' => [
                                        'listID' => "CLASS"
                                    ],
                                    '_value' => "$detail->classification"
                                ]
                            ]
                        ]
                    ]
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$detail->unit_price"
                    ]
                ],
                'cac:ItemPriceExtension' => [
                    'cbc:Amount' => [
                        '_attributes' => [
                            'currencyID' => $invoice->currency,
                        ],
                        '_value' => "$detail->subtotal"
                    ]
                ]
            ];

            if ($detail->country) {
                $originCountry = [
                    'cbc:IdentificationCode' => $detail->country,
                ];

                $iDetail['cac:Item'] = array_merge(
                    ['cbc:Description' => $detail->description],
                    ['cac:OriginCountry' => $originCountry],
                    $iDetail['cac:Item']
                );
            }

            if ($detail->product_tariff_code) {
                $iDetail['cac:Item']['cac:CommodityClassification'][] = [
                    'cbc:ItemClassificationCode' => [
                        '_attributes' => [
                            'listID' => "PTC"
                        ],
                        '_value' => "$detail->product_tariff_code"
                    ]
                ];
            }

            $invoiceDetails[] = $iDetail;
        }
        $document['cac:InvoiceLine'] = $invoiceDetails;
        return $document;
    }

    public function fullAddress($address, $model)
    {
        $addresses = [];

        for ($i = 1; $i < 4; $i++) {
            if (trim($model->{"$address$i"})) {
                $addresses[] = ['cbc:Line' =>  $model->{"$address$i"}];
            }
        }

        $data = [
            'cbc:CityName' => $model->city,
            'cbc:PostalZone' => $model->postcode,
            'cbc:CountrySubentityCode' => $model->state, //is in number have to change
            'cac:AddressLine' => $addresses,
            'cac:Country' => [
                'cbc:IdentificationCode' => [
                    '_attributes' => [
                        'listID' => 'ISO3166-1',
                        'listAgencyID' => '6',
                    ],
                    '_value' => "{$model->country}",
                ]
            ],
        ];

        return $data;
    }

    public function getDocumentRaw($documentUid)
    {
        $uri = "/api/v1.0/documents/$documentUid/raw";

        return $this->request($uri, 'GET', [], self::URL);
    }

    public function getDocumentDetail($documentUid)
    {
        $uri = "/api/v1.0/documents/$documentUid/details";

        return $this->request($uri, 'GET', [], self::URL);
    }

    public function getSubmission($submissionUid)
    {
        $uri = "/api/v1.0/documentsubmissions/$submissionUid";

        return $this->request($uri, 'GET', [], self::URL);
    }

    public function rejectCancelDocument($data)
    {
        $documentUid = $data['document_uid'];
        $data = ['status' => $data['status'], 'reason' => $data['reason']];
        $uri = "/api/v1.0/documents/state/$documentUid/state";

        return $this->request($uri, 'PUT', $data, self::URL);
    }

    public function searchDocument($data)
    {
        $uri = "/api/v1.0/documents/search";
        return $this->request($uri, 'GET', $data, self::URL_PARAM);
    }

    public function getQRCodeURL($doc)
    {
        return "{$this->portalUrl}/{$doc['uuid']}/share/{$doc['longId']}";
    }
}
