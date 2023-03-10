import { find } from 'lodash/fp';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { ReactSelect } from 'common/form/react_select/react_select';

import {
  SelectOption,
  WindowCustomFields,
  WordpressRoleFormItem,
} from '../../types';

interface ParamsType {
  values?: {
    value: string;
  }[];
}

export function validateRadioSelect(item: WordpressRoleFormItem): boolean {
  return typeof item.value === 'string' && item.value.length > 0;
}

type Props = {
  filterIndex: number;
};

export function RadioSelect({ filterIndex }: Props): JSX.Element {
  const segment: WordpressRoleFormItem = useSelect(
    (select) =>
      select('mailpoet-dynamic-segments-form').getSegmentFilter(filterIndex),
    [filterIndex],
  );

  const { updateSegmentFilter } = useDispatch('mailpoet-dynamic-segments-form');

  const customFieldsList: WindowCustomFields = useSelect(
    (select) => select('mailpoet-dynamic-segments-form').getCustomFieldsList(),
    [],
  );
  const customField = find(
    { id: Number(segment.custom_field_id) },
    customFieldsList,
  );
  if (!customField) return null;
  const params = customField.params as ParamsType;
  if (!params || !Array.isArray(params.values)) return null;

  const options = params.values.map((currentValue) => ({
    value: currentValue.value,
    label: currentValue.value,
  }));

  return (
    <ReactSelect
      dimension="small"
      isFullWidth
      placeholder={__('Select value', 'mailpoet')}
      options={options}
      value={
        segment.value ? { value: segment.value, label: segment.value } : null
      }
      onChange={(option: SelectOption): void => {
        void updateSegmentFilter(
          { value: option.value, operator: 'equals' },
          filterIndex,
        );
      }}
      automationId="segment-wordpress-role"
    />
  );
}
