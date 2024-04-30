<?php declare(strict_types=1);

namespace VLPGetResponse\Helper;

class GetResponse
{
    private $api_key;

    private $api_url = 'https://api.getresponse.com/v3';

    public string $lastErrorMsg = '';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    public function get_header() {
        return [
            'X-Auth-Token: api-key ' . $this->api_key,
            'Content-Type: application/json'
        ];
    }

    public function getCampaigns() {
        $post_type = 'GET';
        $post_url = $this->api_url . '/campaigns';
        $post_fields = json_encode([]);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);
        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'getCampaigns()');
            return false;
        }

        return $responseJson;
    }

    public function listContactsByCampaign($campaignId, $email = '') {
        $post_type = 'GET';
        $post_url = $this->api_url . '/campaigns/' . $campaignId . '/contacts';
        $filters = [];
        if($email) {
            $filters['query'] = [
                'email' => $email
            ];
        }
        $post_fields = json_encode($filters);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);

        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'listContactsByCampaign()');
            return false;
        }

        return $responseJson;
    }

    public function searchContact($campaignId, $email) {
        $exists = $this->listContactsByCampaign($campaignId, $email);

        if(is_countable($exists) && count($exists) === 1 && !empty($exists[0])) {
            return $exists[0] ?? false;
        }

        return false;
    }

    public function upsertContact($properties, $contactId = '', $customFields = []) {
        $post_type = 'POST';
        $post_url = $this->api_url . '/contacts';

        if($contactId)
            $post_url .= '/' . $contactId;

        if($customFields)
            $properties['customFieldValues'] = $this->processCustomFields($customFields);

        $post_fields = json_encode($properties);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);

        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'upsertContact()');
            return false;
        }

        return $responseJson;
    }

    public function processCustomFields($customerCustomFields) {
        if(!$customerCustomFields) return [];

        $processedCustomFields = [];
        $grCustomFields = $this->getCustomFields();

        foreach($grCustomFields as $grCustomField) {
            $grCustomFieldId = $grCustomField['customFieldId'];
            $grCustomFieldName = $grCustomField['name'];

            foreach($customerCustomFields as $customerCustomFieldName => $customerCustomFieldValue) {
                if($grCustomFieldName === $customerCustomFieldName && $customerCustomFieldValue) {
                    $processedCustomFields[] = [
                        'customFieldId' => $grCustomFieldId,
                        'value' => [$customerCustomFieldValue]
                    ];
                }
            }
        }

        return $processedCustomFields;
    }

    public function getCustomFields() {
        $post_type = 'GET';
        $post_url = $this->api_url . '/custom-fields';
        $post_fields = json_encode([]);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);
        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'getCampaigns()');
            return false;
        }

        return $responseJson;
    }

    public function searchTag($tagName) {
        $post_type = 'GET';
        $post_url = $this->api_url . '/tags';
        $filters['query'] = [
            'name' => $tagName
        ];
        $post_fields = json_encode($filters);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);

        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'searchTag()');
            return false;
        }

        return $responseJson[0] ?? false;
    }

    public function createTag($properties) {
        $post_type = 'POST';
        $post_url = $this->api_url . '/tags';
        $post_fields = json_encode($properties);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);

        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'createTag()');
            return false;
        }

        return $responseJson;
    }

    public function upsertContactTags($contactId, $tagIds) {
        $post_type = 'POST';
        $post_url = $this->api_url . '/contacts/' . $contactId . '/tags';
        $post_fields = json_encode([
            'tags' => $tagIds
        ]);
        $post_header = $this->get_header();

        $response = $this->curl($post_url, $post_fields, $post_header, $post_type);
        $responseJson = json_decode($response, true);

        if($this->isInvalidResponse($responseJson)) {
            $this->logError($responseJson, 'upsertContactTags()');
            return false;
        }

        return $responseJson;
    }

    /** SYNCING DATA **/
    public function customerToContactData($customer, $fields) {

        $defaultBillingAddress = $customer->getDefaultBillingAddress();
        $company = $customer->getCompany() ? $customer->getCompany() : null;
        $company = !$company && $defaultBillingAddress->getCompany() ? $defaultBillingAddress->getCompany() : $company;

        $contact = [
            'name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'email' => $customer->getEmail(),
            'campaign' => [
                'campaignId' => $fields['campaigns'] ?? ''
            ],
        ];

        $customFields = [
            'company' => $company,
            'phone' => $defaultBillingAddress?->getPhoneNumber(),
            'street' => $defaultBillingAddress?->getStreet(),
            'city' => $defaultBillingAddress?->getCity(),
            'state' => $defaultBillingAddress?->getCountryState()?->getName(),
            'country' => $defaultBillingAddress?->getCountry()?->getName(),
            'postal_code' => $defaultBillingAddress?->getZipcode(),
        ];

        $exists = $this->searchContact($contact['campaign']['campaignId'], $contact['email']);
        $contactId = $exists['contactId'] ?? false;

        // Creates contact if no contactId, else updates contact
        $this->upsertContact($contact, $contactId, $customFields);

        // If has tags, link to customer
        $tags = $customer->getTags();
        if($tags) {
            // Get contactId
            $exists = $this->searchContact($contact['campaign']['campaignId'], $contact['email']);
            $contactId = $exists['contactId'] ?? false;

            if($contactId) {
                $tagIds = [];

                foreach ($tags as $tag) {
                    $tagName = $tag->getName();
                    $tagExists = $this->searchTag($tagName);

                    if (!empty($tagExists['tagId']))
                        $tagIds[] = ['tagId' => $tagExists['tagId']];
                    else {
                        $tag = $this->createTag(['name' => $tagName]);

                        if ($tag && !empty($tag['tagId']))
                            $tagIds[] = ['tagId' => $tag['tagId']];
                    }
                }

                if($tagIds) {
                    $this->upsertContactTags($contactId, $tagIds);
                }
            }
        }

        return $contact;
    }

    /** HELPER FUNCS **/
    
    public function isInvalidResponse($responseJson) {
        if($responseJson === false || (!empty($responseJson['httpStatus']) && $responseJson['httpStatus'] !== 200)) return true;
        return false;
    }

    public function logError($error, $identifier = ''): void {
        $this->lastErrorMsg = $error['message'] ?? '';
        $error = is_array($error) ? json_encode($error) : $error;
        $error = date('Y-m-d H:i:s') . ': ' . $identifier . ' ' .$error;
        $log_file_data = getcwd() .'/../var/log/vlpgetresponse-' . date('Y-m-d') . '.log';
        file_put_contents($log_file_data, $error . "\n", FILE_APPEND);
    }

    // Curl function
    public function curl($post_url, $post_fields, $post_header = false, $post_type = 'GET') {
        // setup cURL request
        $ch = curl_init();

        // do not return header information
        curl_setopt($ch, CURLOPT_HEADER, 0);

        // submit data in header if specified
        if (is_array($post_header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $post_header);
        }

        // do not return status info
        curl_setopt($ch, CURLOPT_VERBOSE, 0);

        // return data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // if using GET, POST or PUT
        if ($post_type == 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        } else if ($post_type == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        } else if ($post_type == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            if (is_array($post_fields)) {
                $post_url .= '?' . http_build_query($post_fields);
            } else if (json_decode($post_fields, true)) {
                $post_url .= '?' . http_build_query(json_decode($post_fields, true));
            }
        }

        // specified endpoint
        curl_setopt($ch, CURLOPT_URL, $post_url);

        // execute cURL request
        $response = curl_exec($ch);

        // return errors if any
        if($response === false) {
            $output = curl_error($ch);
        } else {
            $output = $response;
        }

        // close cURL handle
        curl_close($ch);

        // output
        return $output;
    }
}