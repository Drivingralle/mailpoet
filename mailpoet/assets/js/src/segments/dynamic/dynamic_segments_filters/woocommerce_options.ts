import { MailPoet } from 'mailpoet';
import { SegmentTypes } from '../types';

// WooCommerce
export enum WooCommerceActionTypes {
  NUMBER_OF_ORDERS = 'numberOfOrders',
  PURCHASED_CATEGORY = 'purchasedCategory',
  PURCHASE_DATE = 'purchaseDate',
  PURCHASED_PRODUCT = 'purchasedProduct',
  TOTAL_SPENT = 'totalSpent',
  AVERAGE_SPENT = 'averageSpent',
  CUSTOMER_IN_COUNTRY = 'customerInCountry',
  SINGLE_ORDER_VALUE = 'singleOrderValue',
  USED_PAYMENT_METHOD = 'usedPaymentMethod',
}

export const WooCommerceOptions = [
  {
    value: WooCommerceActionTypes.AVERAGE_SPENT,
    label: MailPoet.I18n.t('wooAverageSpent'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.CUSTOMER_IN_COUNTRY,
    label: MailPoet.I18n.t('wooCustomerInCountry'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.NUMBER_OF_ORDERS,
    label: MailPoet.I18n.t('wooNumberOfOrders'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.PURCHASED_CATEGORY,
    label: MailPoet.I18n.t('wooPurchasedCategory'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.PURCHASE_DATE,
    label: MailPoet.I18n.t('wooPurchaseDate'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.PURCHASED_PRODUCT,
    label: MailPoet.I18n.t('wooPurchasedProduct'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.SINGLE_ORDER_VALUE,
    label: MailPoet.I18n.t('wooSingleOrderValue'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.TOTAL_SPENT,
    label: MailPoet.I18n.t('wooTotalSpent'),
    group: SegmentTypes.WooCommerce,
  },
  {
    value: WooCommerceActionTypes.USED_PAYMENT_METHOD,
    label: MailPoet.I18n.t('wooUsedPaymentMethod'),
    group: SegmentTypes.WooCommerce,
  },
];

// WooCommerce Memberships
export enum WooCommerceMembershipsActionTypes {
  MEMBER_OF = 'isMemberOf',
}

export const WooCommerceMembershipOptions = [
  {
    value: WooCommerceMembershipsActionTypes.MEMBER_OF,
    label: MailPoet.I18n.t('segmentsActiveMembership'),
    group: SegmentTypes.WooCommerceMembership,
  },
];

// WooCommerce Subscriptions
export enum WooCommerceSubscriptionsActionTypes {
  ACTIVE_SUBSCRIPTIONS = 'hasActiveSubscription',
}

export const WooCommerceSubscriptionOptions = [
  {
    value: WooCommerceSubscriptionsActionTypes.ACTIVE_SUBSCRIPTIONS,
    label: MailPoet.I18n.t('segmentsActiveSubscription'),
    group: SegmentTypes.WooCommerceSubscription,
  },
];
