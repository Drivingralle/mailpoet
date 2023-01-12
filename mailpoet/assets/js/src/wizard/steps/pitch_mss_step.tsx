import { useRouteMatch, Switch, Route } from 'react-router-dom';
import { MSSStepFirstPart } from './pitch_mss_step/first_part';
import { MSSStepSecondPart } from './pitch_mss_step/second_part';
import { MSSStepThirdPart } from './pitch_mss_step/third_part';

type WelcomeWizardPitchMSSStepPropType = {
  subscribersCount: number;
  finishWizard: (redirect_url?: string) => void;
};

function WelcomeWizardPitchMSSStep({
  subscribersCount,
  finishWizard,
}: WelcomeWizardPitchMSSStepPropType): JSX.Element {
  const { path } = useRouteMatch();

  return (
    <Switch>
      <Route path={`${path}/part/2`}>
        <MSSStepSecondPart finishWizard={finishWizard} />
      </Route>
      <Route path={`${path}/part/3`}>
        <MSSStepThirdPart finishWizard={finishWizard} />
      </Route>
      <Route path={[`${path}`, `${path}/part/1`]}>
        <MSSStepFirstPart
          subscribersCount={subscribersCount}
          finishWizard={finishWizard}
        />
      </Route>
    </Switch>
  );
}

export { WelcomeWizardPitchMSSStep };
