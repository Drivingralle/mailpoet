import { __, _x } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import {
  SegmentTypes,
  SubscriberActionTypes,
  WordpressRoleFormItem,
} from '../types';
import { WordpressRoleFields } from './subscriber_wordpress_role';
import {
  SubscriberScoreFields,
  validateSubscriberScore,
} from './subscriber_score';
import {
  SubscribedDateFields,
  SubscribedDateOperator,
} from './subscriber_subscribed_date';
import {
  MailPoetCustomFields,
  validateMailPoetCustomField,
} from './subscriber_mailpoet_custom_field';
import {
  SubscribedToList,
  validateSubscribedToList,
} from './subscriber_subscribed_to_list';
import { SubscriberTag, validateSubscriberTag } from './subscriber_tag';

export function validateSubscriber(formItems: WordpressRoleFormItem): boolean {
  if (
    !formItems.action ||
    formItems.action === SubscriberActionTypes.WORDPRESS_ROLE
  ) {
    return (
      Array.isArray(formItems.wordpressRole) &&
      formItems.wordpressRole.length > 0
    );
  }
  if (formItems.action === SubscriberActionTypes.MAILPOET_CUSTOM_FIELD) {
    return validateMailPoetCustomField(formItems);
  }
  if (formItems.action === SubscriberActionTypes.SUBSCRIBER_SCORE) {
    return validateSubscriberScore(formItems);
  }
  if (formItems.action === SubscriberActionTypes.SUBSCRIBED_TO_LIST) {
    return validateSubscribedToList(formItems);
  }
  if (formItems.action === SubscriberActionTypes.SUBSCRIBER_TAG) {
    return validateSubscriberTag(formItems);
  }
  if (!formItems.operator || !formItems.value) {
    return false;
  }
  if (
    formItems.operator === SubscribedDateOperator.BEFORE ||
    formItems.operator === SubscribedDateOperator.AFTER ||
    formItems.operator === SubscribedDateOperator.ON ||
    formItems.operator === SubscribedDateOperator.NOT_ON
  ) {
    const re = /^\d+-\d+-\d+$/;
    return re.test(formItems.value);
  }
  if (
    formItems.operator === SubscribedDateOperator.IN_THE_LAST ||
    formItems.operator === SubscribedDateOperator.NOT_IN_THE_LAST
  ) {
    const re = /^\d+$/;
    return re.test(formItems.value) && Number(formItems.value) > 0;
  }
  return false;
}

export const SubscriberSegmentOptions = [
  {
    value: SubscriberActionTypes.MAILPOET_CUSTOM_FIELD,
    label: __('MailPoet custom field', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
  {
    value: SubscriberActionTypes.SUBSCRIBER_SCORE,
    label: _x('score', 'Subscriber engagement score', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
  {
    value: SubscriberActionTypes.SUBSCRIBED_DATE,
    label: __('subscribed date', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
  {
    value: SubscriberActionTypes.SUBSCRIBED_TO_LIST,
    label: __('subscribed to list', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
  {
    value: SubscriberActionTypes.SUBSCRIBER_TAG,
    label: _x('tag', 'Subscriber tag', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
  {
    value: SubscriberActionTypes.WORDPRESS_ROLE,
    label: __('WordPress user role', 'mailpoet'),
    group: SegmentTypes.WordPressRole,
  },
];

const componentsMap = {
  [SubscriberActionTypes.WORDPRESS_ROLE]: WordpressRoleFields,
  [SubscriberActionTypes.SUBSCRIBER_SCORE]: SubscriberScoreFields,
  [SubscriberActionTypes.SUBSCRIBED_DATE]: SubscribedDateFields,
  [SubscriberActionTypes.MAILPOET_CUSTOM_FIELD]: MailPoetCustomFields,
  [SubscriberActionTypes.SUBSCRIBED_TO_LIST]: SubscribedToList,
  [SubscriberActionTypes.SUBSCRIBER_TAG]: SubscriberTag,
};

type Props = {
  filterIndex: number;
};

export function SubscriberFields({ filterIndex }: Props): JSX.Element {
  const segment: WordpressRoleFormItem = useSelect(
    (select) =>
      select('mailpoet-dynamic-segments-form').getSegmentFilter(filterIndex),
    [filterIndex],
  );

  let Component;
  if (!segment.action) {
    Component = WordpressRoleFields;
  } else {
    Component = componentsMap[segment.action];
  }

  if (!Component) return null;

  return <Component filterIndex={filterIndex} />;
}
