import ReactDOM from 'react-dom';
import { HashRouter, Route } from 'react-router-dom';
import { GlobalContext, useGlobalContextValue } from 'context';
import { Notices } from 'notices/notices.jsx';
import { registerTranslations, withBoundary } from 'common';
import { FormList } from './list.jsx';

function App() {
  return (
    <GlobalContext.Provider value={useGlobalContextValue(window)}>
      <HashRouter>
        <Notices />
        <Route path="*" render={withBoundary(FormList)} />
      </HashRouter>
    </GlobalContext.Provider>
  );
}

const container = document.getElementById('forms_container');

if (container) {
  registerTranslations();
  ReactDOM.render(<App />, container);
}
