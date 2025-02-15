<?php

declare(strict_types=1);

namespace PayonePayment\EventListener;

use PayonePayment\Components\CardRepository\CardRepositoryInterface;
use PayonePayment\PaymentMethod\PayoneCreditCard;
use PayonePayment\Payone\Request\CreditCardCheck\CreditCardCheckRequestFactory;
use PayonePayment\Storefront\Struct\CheckoutCartPaymentData;
use PayonePayment\Storefront\Struct\CheckoutConfirmPaymentData;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Language\LanguageEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmCreditCardEventListener implements EventSubscriberInterface
{
    /** @var CreditCardCheckRequestFactory */
    private $requestFactory;

    /** @var EntityRepositoryInterface */
    private $languageRepository;

    /** @var CardRepositoryInterface */
    private $cardRepository;

    public function __construct(
        CreditCardCheckRequestFactory $requestFactory,
        EntityRepositoryInterface $languageRepository,
        CardRepositoryInterface $cardRepository
    ) {
        $this->requestFactory     = $requestFactory;
        $this->languageRepository = $languageRepository;
        $this->cardRepository     = $cardRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addPayonePageData',
        ];
    }

    public function addPayonePageData(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page    = $event->getPage();
        $context = $event->getSalesChannelContext();

        if ($context->getPaymentMethod()->getId() !== PayoneCreditCard::UUID) {
            return;
        }

        $cardRequest = $this->requestFactory->getRequestParameters($context);
        $savedCards  = $this->cardRepository->getCards($context->getCustomer(), $context->getContext());

        $language = $this->getCustomerLanguage($context->getContext());

        if ($event->getPage()->hasExtension(CheckoutCartPaymentData::EXTENSION_NAME)) {
            $payoneData = $event->getPage()->getExtension(CheckoutCartPaymentData::EXTENSION_NAME);
        } else {
            $payoneData = new CheckoutConfirmPaymentData();
        }

        $payoneData->assign([
            'cardRequest' => $cardRequest,
            'language'    => $language,
            'savedCards'  => $savedCards,
        ]);

        $page->addExtension(CheckoutConfirmPaymentData::EXTENSION_NAME, $payoneData);
    }

    private function getCustomerLanguage(Context $context): string
    {
        $languages = $context->getLanguageId();
        $criteria  = new Criteria([$languages]);
        $criteria->addAssociation('locale');

        /** @var null|LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        if (null === $language || null === $language->getLocale()) {
            return 'en';
        }

        return substr($language->getLocale()->getCode(), 0, 2);
    }
}
