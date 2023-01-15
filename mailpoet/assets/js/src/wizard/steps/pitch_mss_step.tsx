import { useRouteMatch, Switch, Route } from 'react-router-dom';
import { MSSStepFirstPart } from './pitch_mss_step/first_part';
import { MSSStepSecondPart } from './pitch_mss_step/second_part';
import { MSSStepThirdPart } from './pitch_mss_step/third_part';

function WelcomeWizardPitchMSSStep(): JSX.Element {
  const { path } = useRouteMatch();

  return (
    <Switch>
      <Route path={`${path}/part/2`}>
        <MSSStepSecondPart />
      </Route>
      <Route path={`${path}/part/3`}>
        <MSSStepThirdPart />
      </Route>
      <Route path={[`${path}`, `${path}/part/1`]}>
        <MSSStepFirstPart />
      </Route>
    </Switch>
  );
}

export { WelcomeWizardPitchMSSStep };
