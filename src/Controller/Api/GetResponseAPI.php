<?php


namespace VLPGetResponse\Controller\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use VLPGetResponse\Helper\GetResponse;

#[Route(defaults: ['_routeScope' => ['api']])]
class GetResponseAPI extends AbstractController {

    private string $configDomain;

    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly EntityRepository $customerRepository
    ) {
        $this->configDomain = 'VLPGetResponse.config.';
    }

    #[Route(path: '/api/_action/vlpgetresponse/get_campaigns', methods: 'POST')]
    public function getCampaigns(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $salesChannelId = $_POST['sales_channel_id'] ?? null;
        $getResponseKey = $this->configService->get($this->configDomain . "VLPGetResponseAPIKey", $salesChannelId);
        $getResponse = new GetResponse($getResponseKey);

        $campaigns = $getResponse->getCampaigns();

        return new JsonResponse([
            'data' => $campaigns,
            'errorMsg' => $getResponse->lastErrorMsg
        ]);
    }

    #[Route(path: '/api/_action/vlpgetresponse/sync_customers_to_contacs', methods: 'POST')]
    public function syncCustomerContact(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $post_data = $dataBag->all();
        $salesChannelId = $post_data['sales_channel_id'] ?? null;
        $customerGroups = $post_data['customer_groups'] ?? false;
        $fields = $post_data['fields'] ?? false;
        $countries = $post_data['countries'] ?? false;
        $offset = $post_data['offset'] ?? 0;
        $limit = 5;
        $error = 0;

        $getResponseKey = $this->configService->get($this->configDomain . "VLPGetResponseAPIKey", $salesChannelId);
        $getResponse = new GetResponse($getResponseKey);

        $offset = $offset * $limit;

        $customers = $this->getCustomers($offset, $limit, $salesChannelId, $customerGroups, $countries, $context);
        $totalCustomers = $this->getCustomersTotal($salesChannelId, $customerGroups, $countries, $context);

        foreach($customers as $customer) {
            $contact = $getResponse->customerToContactData($customer, $fields);

            if($contact === false) $error++;
        }

        return new JsonResponse([
            'running' => 1,
            'synced' => count($customers),
            'total' => $totalCustomers,
            'error' => $error,
        ]);
    }

    /**
     * Helper functions
     */

    private function get_owner_options($field, $hubspot)
    {
        $owners = $hubspot->get_owners();

        $options = array();
        if($owners) {
            foreach ($owners as $owner) {
                $options[] = array(
                    'value' => $owner['ownerId'],
                    'label' => $owner['firstName'] . ' ' . $owner['lastName'],
                );
            }
        }

        $field['options'] = $options;

        return $field;
    }

    private function get_pipeline_options($field, $hubspot)
    {
        $pipelines = $hubspot->get_deals_pipeline();
        $pipelines = $pipelines['results'] ?? false;

        $options = array();
        if($pipelines) {
            foreach ($pipelines as $pipeline) {
                $options[] = array(
                    'value' => $pipeline['pipelineId'],
                    'label' => $pipeline['label'],
                );
            }
        }

        $field['options'] = $options;

        return $field;
    }

    private function get_dealstage_options($field, $hubspot) {

        $pipelines = $hubspot->get_deals_pipeline();
        $pipelines = $pipelines['results'] ?? false;

        $options = array();
        if($pipelines) {
            foreach ($pipelines as $pipeline) {
                foreach($pipeline['stages'] as $stage) {
                    $options[] = array(
                        'value' => $stage['stageId'],
                        'label' => $stage['label'],
                        'pipeline' => $pipeline['pipelineId'],
                    );
                }
            }
        }

        $field['options'] = $options;

        return $field;
    }

    private function get_all_products($offset, $limit, $salesChannelId, $categoryIds, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('media');
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('options');
        $criteria->addAssociation('categories');
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        // If salesChannelId is provided, filter products from that salesChannel only
        if($salesChannelId)
            $criteria->addFilter(new EqualsAnyFilter('visibilities.salesChannel.id', [$salesChannelId]));

        // If category ids are provided, filter products from those categories only
        if($categoryIds)
            $criteria->addFilter(new EqualsAnyFilter('categoryTree', $categoryIds));

        return $this->productRepository->search($criteria, $context)->getElements();
    }

    private function get_all_products_count($salesChannelId, $categoryIds, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('categories');
        $criteria->addAggregation(new CountAggregation('count-products', 'id'));

        // If salesChannelId is provided, filter products from that salesChannel only
        if($salesChannelId)
            $criteria->addFilter(new EqualsAnyFilter('visibilities.salesChannel.id', [$salesChannelId]));

        // If category ids are provided, filter products from those categories only
        if($categoryIds)
            $criteria->addFilter(new EqualsAnyFilter('categoryTree', $categoryIds));

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->get('count-products');
        return $aggregation->getCount();
    }

    private function getCustomers($offset, $limit, $salesChannelId, $customerGroups, $countries, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('tags');
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        // If salesChannelId is provided, filter customers from that salesChannel and empty (all) salesChannels
        if($salesChannelId)
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsFilter('boundSalesChannelId', null),
                        new EqualsFilter('boundSalesChannelId', $salesChannelId)
                    ]
                )
            );

        // Filter customer groups if present
        if($customerGroups)
            $criteria->addFilter(new EqualsAnyFilter('groupId', $customerGroups));

        // Filter countries if present
        if($countries)
            $criteria->addFilter(new EqualsAnyFilter('addresses.countryId', $countries));

        return $this->customerRepository->search($criteria, $context)->getElements();
    }

    private function getCustomersTotal($salesChannelId, $customerGroups, $countries, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('addresses.country');
        $criteria->addAggregation(new CountAggregation('count-customers', 'id'));

        // If salesChannelId is provided, filter customers from that salesChannel and empty (all) salesChannels
        if($salesChannelId)
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsFilter('boundSalesChannelId', null),
                        new EqualsFilter('boundSalesChannelId', $salesChannelId)
                    ]
                )
            );

        // Filter customer groups if present
        if($customerGroups)
            $criteria->addFilter(new EqualsAnyFilter('groupId', $customerGroups));

        // Filter countries if present
        if($countries)
            $criteria->addFilter(new EqualsAnyFilter('addresses.countryId', $countries));

        $result = $this->customerRepository->aggregate($criteria, $context);
        $aggregation = $result->get('count-customers');
        return $aggregation->getCount();
    }

    private function get_all_orders($offset, $limit, $salesChannelId, $customerGroups, $countries, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.customer.address.country');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('lineItems.product');
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        // If salesChannelId is provided, filter orders from that salesChannel only
        if($salesChannelId)
            $criteria->addFilter(new EqualsAnyFilter('salesChannelId', [$salesChannelId]));

        // Filter customer groups if present
        if($customerGroups)
            $criteria->addFilter(new EqualsAnyFilter('orderCustomer.customer.groupId', $customerGroups));

        // Filter countries if present
        if($countries)
            $criteria->addFilter(new EqualsAnyFilter('orderCustomer.customer.addresses.countryId', $countries));

        return $this->orderRepository->search($criteria, $context)->getElements();
    }

    private function get_all_orders_count($salesChannelId, $customerGroups, $countries, $context) {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('customer.address.country');
        $criteria->addAggregation(new CountAggregation('count-orders', 'id'));

        // If salesChannelId is provided, filter orders from that salesChannel only
        if($salesChannelId)
            $criteria->addFilter(new EqualsAnyFilter('salesChannelId', [$salesChannelId]));

        // Filter customer groups if present
        if($customerGroups)
            $criteria->addFilter(new EqualsAnyFilter('orderCustomer.customer.groupId', $customerGroups));

        // Filter countries if present
        if($countries)
            $criteria->addFilter(new EqualsAnyFilter('orderCustomer.customer.addresses.countryId', $countries));

        $result = $this->orderRepository->aggregate($criteria, $context);
        $aggregation = $result->get('count-orders');
        return $aggregation->getCount();
    }

    private function get_parent_product($productParentId, $context) {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productParentId));
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('media');
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('options');
        $criteria->setLimit(1);

        return $this->productRepository->search($criteria, $context)->first();
    }

    private function update_product_customFields($productId, $productCustomFields, $context) {
        $result = $this->productRepository->update([
            [
                'id' => $productId,
                'customFields' => $productCustomFields
            ]
        ], $context);

        return $result;
    }

    private function product_variant_property_name($product) {
        // Ready property
        $properties = array();
        $propertyOptions = $product->getOptions()->getElements();
        foreach($propertyOptions as $propertyOption) {
            $properties[] = $propertyOption->getName();
        }

        if($properties) {
            return '(' . implode(' - ', $properties) . ')';
        }
        return '';
    }

    private function get_product_url($product, $salesChannel) {
        $productUrl = '';
        $productSeoUrls = $product->getSeoUrls();

        if($salesChannel) {
            $salesChannelId = $salesChannel->getId();
            $salesChannelDomain = $salesChannel->getDomains()->first();
            $salesChannelUrl = $salesChannelDomain ? $salesChannelDomain->getUrl() : '';
            $salesChannelUrlLang = $salesChannelDomain->getLanguageId();

            if($salesChannelId && $salesChannelUrlLang && $productSeoUrls) {
                foreach($productSeoUrls as $productSeoUrl) {
                    $productSeoUrlSalesChannelId = $productSeoUrl->getsalesChannelId();
                    $productSeoUrlLanguageId = $productSeoUrl->getLanguageId();

                    if(
                        $productSeoUrlSalesChannelId == $salesChannelId &&
                        $productSeoUrlLanguageId == $salesChannelUrlLang
                    ) {
                        $pathInfo = $productSeoUrl->getSeoPathInfo();

                        if($salesChannelUrl && $pathInfo)
                            $productUrl = $salesChannelUrl . '/' . $pathInfo;
                    }
                }
            }
        }

        return $productUrl;
    }

    private function get_sales_channel($salesChannelId, $context) {
        if(!$salesChannelId) return false;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        $criteria->addAssociation('domains');
        $criteria->addAssociation('currencies');
        $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));
        $criteria->setLimit(1);

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }

    private function updateOrderCustomFields($orderId, $orderCustomFields, $context) {
        $result = $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => $orderCustomFields
            ]
        ], $context);

        return $result;
    }

    private function updateCustomerCustomFields($customerId, $customerCustomFields, $context) {
        $result = $this->customerRepository->update([
            [
                'id' => $customerId,
                'customFields' => $customerCustomFields
            ]
        ], $context);

        return $result;
    }
}