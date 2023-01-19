import { useContext } from 'react';
import { MailPoet } from 'mailpoet';
import { STORE_NAME } from 'settings/store/store_name';
import { select } from '@wordpress/data';
import { useAction, useSelector, useSetting } from 'settings/store/hooks';
import { GlobalContext } from 'context';
import { Button } from 'common/button/button';
import { t } from 'common/functions';
import { Input } from 'common/form/input/input';
import { MssStatus } from 'settings/store/types';
import { Inputs, Label } from 'settings/components';
import { SetFromAddressModal } from 'common/set_from_address_modal';
import ReactStringReplace from 'react-string-replace';
import { Messages } from 'common/premium_key/messages';

type KeyState = {
  is_approved: boolean;
};

type Props = {
  subscribersCount: number;
};

const premiumTabDescription = ReactStringReplace(
  t('premiumTabDescription'),
  /\[link\](.*?)\[\/link\]/g,
  (text) => (
    <a
      href="https://account.mailpoet.com/account?utm_source=plugin&utm_medium=settings&utm_campaign=activate-existing-plan&ref=settings-key-activation"
      target="_blank"
      rel="noopener noreferrer"
    >
      {text}
    </a>
  ),
);

const premiumTabGetKey = ReactStringReplace(
  t('premiumTabGetKey'),
  /\[link\](.*?)\[\/link\]/g,
  (text) => (
    <a
      href="https://account.mailpoet.com/account?utm_source=plugin&utm_medium=settings&utm_campaign=activate-existing-plan&ref=settings-key-activation"
      target="_blank"
      rel="noopener noreferrer"
    >
      {text}
    </a>
  ),
);

export function KeyActivation({ subscribersCount }: Props) {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { notices } = useContext<any>(GlobalContext);
  const state = useSelector('getKeyActivationState')();
  const setState = useAction('updateKeyActivationState');
  const verifyMssKey = useAction('verifyMssKey');
  const verifyPremiumKey = useAction('verifyPremiumKey');
  const sendCongratulatoryMssEmail = useAction('sendCongratulatoryMssEmail');
  const [senderAddress, setSenderAddress] = useSetting('sender', 'address');
  const [unauthorizedAddresses, setUnauthorizedAddresses] = useSetting(
    'authorized_emails_addresses_check',
  );
  const [apiKeyState] = useSetting('mta', 'mailpoet_api_key_state', 'data');
  const setSaveDone = useAction('setSaveDone');
  const setAuthorizedAddress = async (address: string) => {
    await setSenderAddress(address);
    await setUnauthorizedAddresses(null);
    setSaveDone();
  };

  const showFromAddressModal =
    state.fromAddressModalCanBeShown &&
    state.mssStatus === MssStatus.VALID_MSS_ACTIVE &&
    (!senderAddress || unauthorizedAddresses);

  const showPendingApprovalNotice =
    state.inProgress === false &&
    state.mssStatus === MssStatus.VALID_MSS_ACTIVE &&
    apiKeyState &&
    (apiKeyState as KeyState).is_approved === false;

  const verifyKey = async () => {
    if (!state.key) {
      notices.error(<p>{t('premiumTabNoKeyNotice')}</p>, { scroll: true });
      return;
    }
    await setState({
      mssStatus: null,
      premiumStatus: null,
      premiumInstallationStatus: null,
    });
    MailPoet.Modal.loading(true);
    setState({ inProgress: true });
    await verifyMssKey(state.key);
    const currentMssStatus =
      select(STORE_NAME).getKeyActivationState().mssStatus;
    if (currentMssStatus === MssStatus.VALID_MSS_ACTIVE) {
      await sendCongratulatoryMssEmail();
    }
    await verifyPremiumKey(state.key);
    setState({ inProgress: false });
    MailPoet.Modal.loading(false);
    setState({ fromAddressModalCanBeShown: true });
  };

  async function activationCallback() {
    await verifyMssKey(state.key);
    sendCongratulatoryMssEmail();
    setState({ fromAddressModalCanBeShown: true });
  }

  return (
    <div className="mailpoet-settings-grid">
      <Label
        htmlFor="mailpoet_premium_key"
        title={t('premiumTabActivationKeyLabel')}
        description={
          <>
            {premiumTabDescription}
            <br />
            <br />
            {premiumTabGetKey}
            <br />
            <br />
            {ReactStringReplace(
              t('premiumTabGetPlan'),
              /\[link\](.*?)\[\/link\]/g,
              (text) => (
                <a
                  href={`https://account.mailpoet.com/?s=${subscribersCount}&utm_source=plugin&utm_medium=settings&utm_campaign=create-new-plan&ref=settings-key-activation`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {text}
                </a>
              ),
            )}
          </>
        }
      />
      <Inputs>
        <Input
          type="text"
          id="mailpoet_premium_key"
          name="premium[premium_key]"
          value={state.key || ''}
          onChange={(event) =>
            setState({
              mssStatus: null,
              premiumStatus: null,
              premiumInstallationStatus: null,
              key: event.target.value.trim() || null,
            })
          }
        />
        <Button type="button" onClick={verifyKey}>
          {t('premiumTabVerifyButton')}
        </Button>
        {state.isKeyValid !== null &&
          Messages(state, showPendingApprovalNotice, activationCallback)}
      </Inputs>
      {showFromAddressModal && (
        <SetFromAddressModal
          onRequestClose={() => {
            setState({ fromAddressModalCanBeShown: false });
            sendCongratulatoryMssEmail();
          }}
          setAuthorizedAddress={setAuthorizedAddress}
        />
      )}
    </div>
  );
}
