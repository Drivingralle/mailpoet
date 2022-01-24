import FeaturesController from 'features_controller';
import MailPoetComUrlFactory from 'mailpoet_com_url_factory';
import { MailPoetDate as Date } from 'date';
// A placeholder for MailPoet object
var MailPoet = {
  FeaturesController: FeaturesController(window.mailpoet_feature_flags),
  MailPoetComUrlFactory: MailPoetComUrlFactory(window.mailpoet_referral_id),
  version: window.mailpoet_version,
  premiumVersion: window.mailpoet_premium_version,
  premiumLink: window.mailpoet_premium_link,
  isWoocommerceActive: window.mailpoet_woocommerce_active,
  premiumActive: window.mailpoet_premium_active,
  subscribersLimit: window.mailpoet_subscribers_limit,
  subscribersLimitReached: window.mailpoet_subscribers_limit_reached,
  subscribersCount: window.mailpoet_subscribers_count,
  hasPremiumSupport: window.mailpoet_has_premium_support,
  hasValidApiKey: window.mailpoet_has_valid_api_key,
  hasValidPremiumKey: window.mailpoet_has_valid_premium_key,
  hasInvalidMssApiKey: window.mailpoet_mss_key_invalid,
  mtaMethod: window.mailpoet_mta_method,
  listingPerPage: window.mailpoet_listing_per_page,
  libs3rdPartyEnabled: window.mailpoet_3rd_party_libs_enabled,
  apiVersion: window.mailpoet_api_version,
  emailRegex: window.mailpoet_email_regex,
  wpSegmentState: window.mailpoet_wp_segment_state,
  wpWeekStartsOn: window.mailpoet_wp_week_starts_on,
  subscribersCountsCacheCreatedAt: window.mailpoet_subscribers_counts_cache_created_at,
  getShortcodeLinks: () => (window.mailpoet_shortcode_links ? window.mailpoet_shortcode_links : []),
  settings: window.mailpoet_settings,
  trackingConfig: window.mailpoet_tracking_config || {},
  Date,
  isPremiumPluginInstalled: window.mailpoet_premium_plugin_installed,
  premiumPluginDownloadUrl: window.mailpoet_premium_plugin_download_url,
  premiumPluginActivationUrl: window.mailpoet_premium_plugin_activation_url,
  pluginPartialKey: window.mailpoet_plugin_partial_key,
};

// Expose MailPoet globally
window.MailPoet = MailPoet;

export default MailPoet;

require('ajax'); // side effect - extends MailPoet object
require('i18n'); // side effect - extends MailPoet object
require('modal'); // side effect - extends MailPoet object
require('notice'); // side effect - extends MailPoet object
require('num'); // side effect - extends MailPoet object
require('analytics_event'); // side effect - extends MailPoet object
require('help-tooltip'); // side effect - extends MailPoet object
