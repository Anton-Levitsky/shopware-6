<?php

declare(strict_types=1);

namespace PayonePayment\Storefront\Controller\Account;

use PayonePayment\Components\CardRepository\CardRepositoryInterface;
use PayonePayment\Storefront\Page\Card\AccountCardPageLoader;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class AccountCardController extends StorefrontController
{
    /** @var AccountCardPageLoader */
    private $accountCardPageLoader;

    /** @var CardRepositoryInterface */
    private $cardRepository;

    public function __construct(AccountCardPageLoader $accountCardPageLoader, CardRepositoryInterface $cardRepository)
    {
        $this->accountCardPageLoader = $accountCardPageLoader;
        $this->cardRepository        = $cardRepository;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/account/card/overview", name="frontend.account.payone.card.page", options={"seo": "false"}, methods={"GET"})
     */
    public function cardOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountCardPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/payone/account/card.html.twig', ['page' => $page]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/account/card/delete", name="frontend.account.payone.card.delete", options={"seo": "false"}, methods={"GET"})
     */
    public function deleteCard(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->cardRepository->removeCard(
                $context->getCustomer(),
                $request->get('pseudoCardPan'),
                $context->getContext()
            );
        } catch (Throwable $exception) {
            $this->addFlash('danger', $this->trans('PayonePayment.cardPage.error'));

            return $this->forwardToRoute('frontend.account.payone.card.page');
        }

        $this->addFlash('success', $this->trans('PayonePayment.cardPage.success'));

        return new RedirectResponse($this->generateUrl('frontend.account.payone.card.page'));
    }
}
