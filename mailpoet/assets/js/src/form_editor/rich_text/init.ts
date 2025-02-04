import '@wordpress/format-library'; // load default formats (bold, italic, ...)
import { registerFormatType } from '@wordpress/rich-text';
import * as FontSelectionFormat from './font_selection_format';

export function initRichText(): void {
  registerFormatType(FontSelectionFormat.name, FontSelectionFormat.settings);
}
