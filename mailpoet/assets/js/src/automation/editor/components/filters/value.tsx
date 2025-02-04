import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { getSettings, dateI18n } from '@wordpress/date';
import { Filter } from '../automation/types';
import { storeName } from '../../store';

const getValue = ({
  field_key,
  args,
  condition,
}: Filter): string | undefined => {
  const field = select(storeName).getRegistry().fields[field_key];
  switch (field?.type) {
    case 'boolean':
      return args.value ? __('Yes', 'mailpoet') : __('No', 'mailpoet');
    case 'number':
    case 'integer':
      if (args.value === undefined) {
        return undefined;
      }
      return Array.isArray(args.value)
        ? args.value.join(' and ')
        : args.value.toString();
    case 'string':
      return args.value as string;
    case 'datetime': {
      if (args.value === undefined) {
        return undefined;
      }

      const settings = getSettings();

      // in-the-last/not-in-the-last
      if (
        ['in-the-last', 'not-in-the-last'].includes(condition) &&
        typeof args.value === 'object' &&
        'number' in args.value &&
        'unit' in args.value
      ) {
        return `${args.value.number as number} ${
          {
            days: __('days', 'mailpoet'),
            weeks: __('weeks', 'mailpoet'),
            months: __('months', 'mailpoet'),
          }[args.value.unit as string] ?? __('unknown unit', 'mailpoet')
        }`;
      }

      // on-the-days-of-the-week
      if (condition === 'on-the-days-of-the-week') {
        return (Array.isArray(args.value) ? args.value : [])
          .map(
            (day: number) =>
              (settings.l10n.weekdays[day] as string) ??
              __('unknown day', 'mailpoet'),
          )
          .join(', ');
      }

      const isDate = condition === 'on' || condition === 'not-on';

      return dateI18n(
        isDate ? settings.formats.date : settings.formats.datetime,
        args.value as string,
        settings.timezone.string,
      );
    }
    case 'enum':
    case 'enum_array': {
      const options = (field.args.options ?? []) as {
        id: string;
        name: string;
      }[];
      const values = Array.isArray(args.value) ? args.value : [args.value];
      const labels = values
        .map((v) => options.find(({ id }) => id === v)?.name)
        .filter((v) => v !== undefined);

      if (labels.length === 0) {
        return __('Unknown value', 'mailpoet');
      }

      const suffix =
        labels.length < values.length
          ? __('and unknown values', 'mailpoet')
          : '';
      return `${labels.join(', ')}${suffix}`;
    }
    default:
      return __('Unknown value', 'mailpoet');
  }
};

type Props = {
  filter: Filter;
};

export function Value({ filter }: Props): JSX.Element | null {
  const value = getValue(filter);
  if (value === undefined) {
    return null;
  }
  return (
    <span className="mailpoet-automation-filters-list-item-value">{value}</span>
  );
}
