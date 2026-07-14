<?php
namespace SerbenConnect\Components;

use SerbenConnect\Components\Profile\NameComponent;
use SerbenConnect\Components\Profile\FirstNameComponent;
use SerbenConnect\Components\Profile\CpfComponent;
use SerbenConnect\Components\Profile\EmailComponent;
use SerbenConnect\Components\Profile\PhoneComponent;
use SerbenConnect\Components\Profile\PhotoComponent;
use SerbenConnect\Components\Plan\PlanNameComponent;
use SerbenConnect\Components\Plan\PlanStatusComponent;
use SerbenConnect\Components\Card\CardNumberComponent;
use SerbenConnect\Components\Card\CardStatusComponent;
use SerbenConnect\Components\Card\CardExpirationComponent;
use SerbenConnect\Components\Financial\PointsComponent;
use SerbenConnect\Components\Financial\CashbackComponent;
use SerbenConnect\Components\Financial\CreditBalanceComponent;
use SerbenConnect\Components\Financial\PaymentStatusComponent;
use SerbenConnect\Components\Financial\NextDueDateComponent;
use SerbenConnect\Components\Cards\PointsCardComponent;
use SerbenConnect\Components\Cards\CashbackCardComponent;
use SerbenConnect\Components\Cards\PlanCardComponent;
use SerbenConnect\Components\Club\ClubStatusComponent;
use SerbenConnect\Components\Club\StoreIdComponent;
use SerbenConnect\Components\Club\UsesPointsComponent;
use SerbenConnect\Components\Club\UsesCashbackComponent;
use SerbenConnect\Components\Club\UsesCreditComponent;
use SerbenConnect\Components\Club\FastRegistrationComponent;
use SerbenConnect\Components\Contracts\ContractsComponent;
use SerbenConnect\Components\Contracts\CurrentPlanComponent;
use SerbenConnect\Components\Contracts\ContractStatusComponent;
use SerbenConnect\Components\Plans\PlansComponent;
use SerbenConnect\Components\Partners\PartnersComponent;
use SerbenConnect\Components\Partners\PartnerCategoriesComponent;
use SerbenConnect\Components\Partners\PartnerFieldComponent;
use SerbenConnect\Components\Partners\PartnerCardComponent;
use SerbenConnect\Components\Partners\PartnersDebugComponent;
use SerbenConnect\Components\Partners\PartnerFiltersComponent;
use SerbenConnect\Components\Partners\PartnersCountComponent;
use SerbenConnect\Components\Partners\PartnerCategoriesDebugComponent;
use SerbenConnect\Components\Account\DigitalCardComponent;
use SerbenConnect\Components\Account\WalletComponent;
use SerbenConnect\Components\Account\ProfileCardComponent;
use SerbenConnect\Components\Account\ContractCardComponent;
use SerbenConnect\Components\Account\PlanCardComponent as AccountPlanCardComponent;
use SerbenConnect\Components\Account\QuickActionsComponent;
use SerbenConnect\Components\WooCommerce\PlanPurchaseButtonComponent;
use SerbenConnect\Components\Dependents\DependentsComponent;
use SerbenConnect\Components\Dependents\DependentsCountComponent;
use SerbenConnect\Components\Dependents\RelationshipOptionsComponent;

if (!defined('ABSPATH')) { exit; }

class ComponentRegistry
{
    private $instances;

    public function all(): array
    {
        if (is_array($this->instances)) {
            return $this->instances;
        }

        $this->instances = [
            new NameComponent(), new FirstNameComponent(), new CpfComponent(),
            new EmailComponent(), new PhoneComponent(), new PhotoComponent(),
            new PlanNameComponent(), new PlanStatusComponent(),
            new CardNumberComponent(), new CardStatusComponent(), new CardExpirationComponent(),
            new PointsComponent(), new CashbackComponent(), new CreditBalanceComponent(),
            new PaymentStatusComponent(), new NextDueDateComponent(),
            new PointsCardComponent(), new CashbackCardComponent(), new PlanCardComponent(),
            new ClubStatusComponent(), new StoreIdComponent(), new UsesPointsComponent(),
            new UsesCashbackComponent(), new UsesCreditComponent(), new FastRegistrationComponent(),
            new ContractsComponent(), new CurrentPlanComponent(), new ContractStatusComponent(),
            new PlansComponent(), new PartnersComponent(), new PartnerCategoriesComponent(),
            new PartnerFieldComponent(), new PartnerCardComponent(), new PartnersDebugComponent(),
            new PartnerFiltersComponent(), new PartnersCountComponent(), new PartnerCategoriesDebugComponent(),
            new DigitalCardComponent(), new WalletComponent(), new ProfileCardComponent(),
            new ContractCardComponent(), new AccountPlanCardComponent(), new QuickActionsComponent(),
            new PlanPurchaseButtonComponent(),
            new DependentsComponent(), new DependentsCountComponent(), new RelationshipOptionsComponent(),
        ];

        return $this->instances;
    }

    public function find(string $key): ?BaseComponent
    {
        $key = sanitize_key($key);
        foreach ($this->all() as $component) {
            if ($component instanceof BaseComponent && sanitize_key($component->shortcode()) === $key) {
                return $component;
            }
        }

        return null;
    }
}
