<?php

use Carbon\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

if (!function_exists('canonicalize')) {
    function canonicalize($content)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $xml = str_replace(["\r\n", "\r", "\n"], "", $content);
        $dom->loadXML($xml);
        $canonicalizedXml = $dom->C14N();
        return $canonicalizedXml;
    }
}

if (!function_exists('signHash')) {
    function signHash($content)
    {
        $path = base_path('certificate/private_key.pem');
        $key = file_get_contents($path);
        $signature = '';
        openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);

        if (verifyHash($content, $signature) === 1) {
            return base64_encode($signature);
        }

        return null;
    }
}

if (!function_exists('verifyHash')) {
    function verifyHash($content, $signature)
    {
        $path = base_path('certificate/certificate_original.pem');
        $cert = file_get_contents($path);
        $verified = openssl_verify($content, $signature, $cert, OPENSSL_ALGO_SHA256);

        return $verified;
    }
}

if (!function_exists('hashCertificate')) {
    function hashCertificate()
    {
        $path = base_path('certificate/certificate.pem');
        $cert = file_get_contents($path);
        $cert = str_replace(["\r\n", "\r", "\n"], "", $cert);
        $cert = base64_decode($cert);
        $certificateHash = hash('sha256', $cert);
        $certificateHash = base64_encode(hex2bin($certificateHash));

        return $certificateHash;
    }
}

if (!function_exists('addSignedProperties')) {
    function addSignedProperties($certDigest, $signatureValue, $docDigest)
    {
        $path = base_path('certificate/certificate.pem');
        $cert = file_get_contents($path);
        $cert = str_replace(["\r\n", "\r", "\n"], "", $cert);
        $issuerName = 'CN=LHDNM Sub CA G3, OU=Terms of use at http://www.posdigicert.com.my, O=LHDNM, C=MY';
        $serialNumber = '19641061';
        $time = Carbon::now()->format('Y-m-d\TH:i:s\Z');
        $propDigest = generateSignedPropertiesHash($certDigest, $issuerName, $serialNumber, $time);
        $document = [
            'ext:UBLExtensions' => [
                'ext:UBLExtension' => [
                    'ext:ExtensionURI' => 'urn:oasis:names:specification:ubl:dsig:enveloped:xades',
                    'ext:ExtensionContent' => [
                        'sig:UBLDocumentSignatures' => [
                            '_attributes' => [
                                'xmlns:sig' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2',
                                'xmlns:sac' => 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2',
                                'xmlns:sbc' => 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2',
                            ],
                            'sac:SignatureInformation' => [
                                'cbc:ID' => 'urn:oasis:names:specification:ubl:signature:1',
                                'sbc:ReferencedSignatureID' => 'urn:oasis:names:specification:ubl:signature:Invoice',
                                'ds:Signature' => [
                                    '_attributes' => [
                                        'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#',
                                        'Id' => 'signature'
                                    ],
                                    'ds:SignedInfo' => [
                                        'ds:CanonicalizationMethod' => [
                                            '_attributes' => [
                                                'Algorithm' => 'http://www.w3.org/2006/12/xml-c14n11',
                                            ]
                                        ],
                                        'ds:SignatureMethod' => [
                                            '_attributes' => [
                                                'Algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
                                            ]
                                        ],
                                        'ds:Reference' => [
                                            [
                                                '_attributes' => [
                                                    'Id' => 'id-doc-signed-data',
                                                    'URI' => '',
                                                ],
                                                'ds:Transforms' => [
                                                    [
                                                        'ds:Transform' => [
                                                            [
                                                                '_attributes' => ['Algorithm' => 'http://www.w3.org/TR/1999/REC-xpath-19991116'],
                                                                'ds:XPath' => 'not(//ancestor-or-self::ext:UBLExtensions)'
                                                            ], [
                                                                '_attributes' => ['Algorithm' => 'http://www.w3.org/TR/1999/REC-xpath-19991116'],
                                                                'ds:XPath' => 'not(//ancestor-or-self::cac:Signature)'
                                                            ], [
                                                                '_attributes' => ['Algorithm' => 'http://www.w3.org/2006/12/xml-c14n11'],
                                                            ]
                                                        ]
                                                    ],
                                                ],
                                                'ds:DigestMethod' => [
                                                    '_attributes' => [
                                                        'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256'
                                                    ]
                                                ],
                                                'ds:DigestValue' => $docDigest
                                            ],
                                            [
                                                '_attributes' => [
                                                    'Type' => 'http://www.w3.org/2000/09/xmldsig#SignatureProperties',
                                                    'URI' => '#id-xades-signed-props'
                                                ],
                                                'ds:DigestMethod' => [
                                                    '_attributes' => [
                                                        'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256'
                                                    ]
                                                ],
                                                'ds:DigestValue' => $propDigest
                                            ]
                                        ],
                                    ],
                                    'ds:SignatureValue' => $signatureValue,
                                    'ds:KeyInfo' => [
                                        'ds:X509Data' => [
                                            'ds:X509Certificate' => $cert
                                        ]
                                    ],
                                    'ds:Object' => [
                                        'xades:QualifyingProperties' => [
                                            '_attributes' => [
                                                'xmlns:xades' => 'http://uri.etsi.org/01903/v1.3.2#',
                                                'Target' => 'signature'
                                            ],
                                            'xades:SignedProperties' => [
                                                '_attributes' => [
                                                    'Id' => 'id-xades-signed-props'
                                                ],
                                                'xades:SignedSignatureProperties' => [
                                                    'xades:SigningTime' => $time,
                                                    'xades:SigningCertificate' => [
                                                        'xades:Cert' => [
                                                            'xades:CertDigest' => [
                                                                'ds:DigestMethod' => [
                                                                    '_attributes' => [
                                                                        'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256'
                                                                    ]
                                                                ],
                                                                'ds:DigestValue' => $certDigest,
                                                            ],
                                                            'xades:IssuerSerial' => [
                                                                'ds:X509IssuerName' => $issuerName,
                                                                'ds:X509SerialNumber' => $serialNumber
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $root = [
            'rootElementName' => 'Invoice',
            '_attributes' => [
                'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2'
            ]
        ];
        $xml = ArrayToXml::convert($document, $root);
        $xml = str_replace('<?xml version="1.0"?>', "", $xml);
        $xml = trim(str_replace('</Invoice>', "", $xml));
        return $xml;
    }
}

if (!function_exists('generateSignedPropertiesHash')) {
    function generateSignedPropertiesHash($certDigest, $issuerName, $serialNumber, $time)
    {
        $signedPropertiesXml = [
            // 'xades:SignedProperties' => [
            //     '_attributes' => [
            //         'Id' => 'id-xades-signed-props'
            //     ],
            'xades:SignedSignatureProperties' => [
                'xades:SigningTime' => $time,
                'xades:SigningCertificate' => [
                    'xades:Cert' => [
                        'xades:CertDigest' => [
                            'ds:DigestMethod' => [
                                '_attributes' => [
                                    'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256'
                                ]
                            ],
                            'ds:DigestValue' => $certDigest
                        ],
                        'xades:IssuerSerial' => [
                            'ds:X509IssuerName' => $issuerName,
                            'ds:X509SerialNumber' => $serialNumber
                        ]
                    ]
                ]
            ]
            // ]
        ];

        $signedPropertiesXml2 = [
            'xades:SignedProperties' => [
                '_attributes' => [
                    'Id' => 'id-xades-signed-props',
                    'xmlns:xades' => 'http://uri.etsi.org/01903/v1.3.2#',
                ],
                'xades:SignedSignatureProperties' => [
                    'xades:SigningTime' => $time,
                    'xades:SigningCertificate' => [
                        'xades:Cert' => [
                            'xades:CertDigest' => [
                                'ds:DigestMethod' => [
                                    '_attributes' => [
                                        'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                                        'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#',
                                    ]
                                ],
                                'ds:DigestValue' => [
                                    '_attributes' => [
                                        'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#',
                                    ],
                                    '_value' => $certDigest
                                ]
                            ],
                            'xades:IssuerSerial' => [
                                'ds:X509IssuerName' =>  [
                                    '_attributes' => [
                                        'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#'
                                    ],
                                    '_value' => $issuerName
                                ],
                                'ds:X509SerialNumber' =>  [
                                    '_attributes' => [
                                        'xmlns:ds' => 'http://www.w3.org/2000/09/xmldsig#'
                                    ],
                                    '_value' => $serialNumber
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $xml = ArrayToXml::convert($signedPropertiesXml2);
        $xml = str_replace('<root>', "", $xml);
        $xml = str_replace('</root>', "", $xml);
        $xml = str_replace('<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"/>', '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" />', $xml);
        $xml = trim(str_replace('<?xml version="1.0"?>', "", $xml));

        $hash = hash('sha256', $xml);
        $base64Encoded = base64_encode(hex2bin($hash));


        return $base64Encoded;
    }
}

if (!function_exists('snakeToPascal')) {
    function snakeToPascal($string)
    {
        return ucfirst(Str::camel($string));
    }
}

if (!function_exists('generateQRCode')) {
    function generateQRCode($url)
    {
        return QrCode::size(100)->generate($url);
    }
}
