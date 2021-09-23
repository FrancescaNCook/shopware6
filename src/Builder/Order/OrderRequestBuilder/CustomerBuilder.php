<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is provided with Magento in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Copyright © 2021 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 *
 */

declare(strict_types=1);

namespace MultiSafepay\Shopware6\Builder\Order\OrderRequestBuilder;

use MultiSafepay\Api\Transactions\OrderRequest;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\CustomerDetails;
use MultiSafepay\Shopware6\Util\RequestUtil;
use MultiSafepay\ValueObject\Customer\Address;
use MultiSafepay\ValueObject\Customer\AddressParser;
use MultiSafepay\ValueObject\Customer\Country;
use MultiSafepay\ValueObject\Customer\EmailAddress;
use MultiSafepay\ValueObject\Customer\PhoneNumber;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerBuilder implements OrderRequestBuilderInterface
{
    /**
     * @var RequestUtil
     */
    private $requestUtil;

    /**
     * CustomerBuilder constructor.
     *
     * @param RequestUtil $requestUtil
     */
    public function __construct(
        RequestUtil $requestUtil
    ) {
        $this->requestUtil = $requestUtil;
    }

    /**
     * @param OrderRequest $orderRequest
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     */
    public function build(
        OrderRequest $orderRequest,
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        $request = $this->requestUtil->getGlobals();
        $customer = $salesChannelContext->getCustomer();
        $defaultBillingAddress = $customer->getDefaultBillingAddress();
        [$billingStreet, $billingHouseNumber] =
            (new AddressParser())->parse($defaultBillingAddress->getStreet());

        $orderRequestAddress = (new Address())->addCity($defaultBillingAddress->getCity())
            ->addCountry(new Country(
                $defaultBillingAddress->getCountry() ? $defaultBillingAddress->getCountry()->getIso() : ''
            ))
            ->addHouseNumber($billingHouseNumber)
            ->addStreetName($billingStreet)
            ->addZipCode(trim($defaultBillingAddress->getZipcode()));

        if ($defaultBillingAddress->getCountryState() !== null) {
            $orderRequestAddress->addState($defaultBillingAddress->getCountryState()->getName());
        }

        $customerDetails = (new CustomerDetails())->addLocale($this->getTranslatedLocale($request->getLocale()))
            ->addFirstName($defaultBillingAddress->getFirstName())
            ->addLastName($defaultBillingAddress->getLastName())
            ->addAddress($orderRequestAddress)
            ->addPhoneNumber(new PhoneNumber($defaultBillingAddress->getPhoneNumber() ?? ''))
            ->addEmailAddress(new EmailAddress($customer->getEmail()))
            ->addUserAgent($request->headers->get('User-Agent'))
            ->addReferrer($request->server->get('HTTP_REFERER'))
            ->addReference($customer->getGuest() ? '' : $customer->getId());

        $orderRequest->addCustomer($customerDetails);
    }

    /**
     * @param $locale
     * @return string
     */
    public function getTranslatedLocale(?string $locale): string
    {
        switch ($locale) {
            case 'nl':
                $translatedLocale = 'nl_NL';
                break;
            case 'de':
                $translatedLocale = 'de_DE';
                break;
            default:
                $translatedLocale = 'en_GB';
                break;
        }

        return $translatedLocale;
    }
}