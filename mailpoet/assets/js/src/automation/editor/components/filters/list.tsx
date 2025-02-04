import { useCallback, useState } from 'react';
import { Hooks } from 'wp-js-hooks';
import { Button, RadioControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, closeSmall } from '@wordpress/icons';
import { Value } from './value';
import { Filter } from '../automation/types';
import { storeName } from '../../store';
import { PremiumModal } from '../../../../common/premium_modal';
import {
  DeleteStepFilterType,
  FilterGroupOperatorChangeType,
} from '../../../types/filters';

export function FiltersList(): JSX.Element | null {
  const [showPremiumModal, setShowPremiumModal] = useState(false);

  const { step, fields, filters } = useSelect(
    (select) => ({
      step: select(storeName).getSelectedStep(),
      fields: select(storeName).getRegistry().fields,
      filters: select(storeName).getRegistry().filters,
    }),
    [],
  );

  const onOperatorChange = useCallback(
    (stepId: string, groupId: string, operator: 'and' | 'or') => {
      const operatorChangeCallback: FilterGroupOperatorChangeType =
        Hooks.applyFilters(
          'mailpoet.automation.filters.group_operator_change_callback',
          () => setShowPremiumModal(true),
        );
      operatorChangeCallback(stepId, groupId, operator);
    },
    [],
  );

  const onDelete = useCallback((stepId: string, filter: Filter) => {
    const deleteFilterCallback: DeleteStepFilterType = Hooks.applyFilters(
      'mailpoet.automation.filters.delete_step_filter_callback',
      () => setShowPremiumModal(true),
    );
    deleteFilterCallback(stepId, filter);
  }, []);

  const groups = step.filters?.groups ?? [];
  if (groups.length === 0) {
    return null;
  }

  return (
    <>
      {showPremiumModal && (
        <PremiumModal
          onRequestClose={() => {
            setShowPremiumModal(false);
          }}
          tracking={{
            utm_medium: 'upsell_modal',
            utm_campaign: 'automation_premium_filters',
          }}
        >
          {__('Managing trigger filters is a premium feature.', 'mailpoet')}
        </PremiumModal>
      )}

      {groups.map((group) => (
        <div key={group.id}>
          {group.filters.length > 1 && (
            <RadioControl
              className="mailpoet-automation-filters-list-group-operator"
              selected={group.operator}
              onChange={(value) =>
                onOperatorChange(step.id, group.id, value as 'and' | 'or')
              }
              options={[
                { label: __('All conditions', 'mailpoet'), value: 'and' },
                { label: __('Any condition', 'mailpoet'), value: 'or' },
              ]}
            />
          )}

          <div className="mailpoet-automation-filters-list">
            {group.filters.map((filter) => (
              <div
                key={filter.id}
                className="mailpoet-automation-filters-list-item"
              >
                <div className="mailpoet-automation-filters-list-item-content">
                  <span className="mailpoet-automation-filters-list-item-field">
                    {fields[filter.field_key]?.name ??
                      sprintf(
                        __('Unknown field "%s"', 'mailpoet'),
                        filter.field_key,
                      )}
                  </span>{' '}
                  <span className="mailpoet-automation-filters-list-item-condition">
                    {filters[filter.field_type]?.conditions.find(
                      ({ key }) => key === filter.condition,
                    )?.label ?? __('unknown condition', 'mailpoet')}
                  </span>{' '}
                  <Value filter={filter} />
                </div>
                <Button
                  className="mailpoet-automation-filters-list-item-remove"
                  isSmall
                  onClick={() => onDelete(step.id, filter)}
                >
                  <Icon icon={closeSmall} />
                </Button>
              </div>
            ))}
          </div>
        </div>
      ))}
    </>
  );
}
