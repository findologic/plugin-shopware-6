<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Controller;

use FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class ExportController extends AbstractController implements EventSubscriberInterface
{
    private const
        DEFAULT_START_PARAM = 0,
        DEFAULT_COUNT_PARAM = 20;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/findologic", name="frontend.findologic.export", options={"seo"="false"}, methods={"GET"})
     */
    public function export(Request $request, SalesChannelContext $context): Response
    {
        $this->validateParams($request);
        $shopkey = $request->get('shopkey');
        $salesChannelContext = $this->getSalesChannelContext($shopkey, $context);

        return new Response();
    }

    private function validateParams(Request $request): void
    {
        $shopkey = $request->get('shopkey');
        $start = $request->get('start', self::DEFAULT_START_PARAM);
        $count = $request->get('count', self::DEFAULT_COUNT_PARAM);

        $validator = Validation::createValidator();
        $shopkeyViolations = $validator->validate($shopkey, [
            new NotBlank(),
            new Assert\Regex([
                'pattern' => '/^[A-F0-9]{32}$/'
            ])
        ]);
        if (count($shopkeyViolations) > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Required argument "shopkey" was not given, or does not match the shopkey schema "%s"',
                    $shopkey
                )
            );
        }

        $startViolations = $validator->validate($start, [
            new Assert\Type([
                'type' => 'integer',
                'message' => 'The value {{ value }} is not a valid {{ type }}',
            ]),
            new Assert\GreaterThanOrEqual([
                'value' => 0,
                'message' => 'The value {{ value }} is not greater than or equal to zero'
            ])
        ]);
        if (count($startViolations) > 0) {
            throw new InvalidArgumentException($startViolations->get(0)->getMessage());
        }

        $countViolations = $validator->validate($count, [
            new Assert\Type([
                'type' => 'integer',
                'message' => 'The value {{ value }} is not a valid {{ type }}',
            ]),
            new Assert\GreaterThan([
                'value' => 0,
                'message' => 'The value {{ value }} is not greater than zero'
            ])
        ]);
        if (count($countViolations) > 0) {
            throw new InvalidArgumentException($countViolations->get(0)->getMessage());
        }
    }

    /**
     * @throws UnknownShopkeyException
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalesChannelContext(string $shopkey, SalesChannelContext $currentContext): SalesChannelContext
    {
        $systemConfigRepository = $this->container->get('system_config.repository');
        $systemConfigEntities = $systemConfigRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('configurationKey', 'FinSearch.config.shopkey')),
            $currentContext->getContext()
        );

        foreach ($systemConfigEntities as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationValue() === $shopkey) {
                // If there is no sales channel assigned, we will return the current context
                if ($systemConfigEntity->getSalesChannelId() === null) {
                    return $currentContext;
                }

                $factory = new SalesChannelContextFactory(
                    $this->container->get('sales_channel.repository'),
                    $this->container->get('currency.repository'),
                    $this->container->get('customer.repository'),
                    $this->container->get('customer_group.repository'),
                    $this->container->get('country.repository'),
                    $this->container->get('tax.repository'),
                    $this->container->get('customer_address.repository'),
                    $this->container->get('payment_method.repository'),
                    $this->container->get('shipping_method.repository'),
                    Kernel::getConnection(),
                    $this->container->get('country_state.repository'),
                    new TaxDetector()
                );

                return $factory->create($currentContext->getToken(), $systemConfigEntity->getSalesChannelId());
            }
        }

        throw new UnknownShopkeyException(sprintf('Given shopkey "%s" is not assigned to any shop', $shopkey));
    }
}
