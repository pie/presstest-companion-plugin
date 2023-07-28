import React from 'react';
import { Tab, Tabs, TabList, TabPanel } from 'react-tabs';
import Testing from './components/Testing';
import Reports from './components/Reports';
import '../../../node_modules/react-tabs/style/react-tabs.scss';
import '../../sass/apps/my-account-app.scss';

function App() {
  return (
    <div>
      <h2>Testing Platform</h2>
      <Tabs>
        <TabList>
          <Tab>Testing</Tab>
          <Tab>Reports</Tab>
        </TabList>

        {/* Force rendering here to maintain state for user options when switching between tabs */}
        <TabPanel forceRender>
          <Testing />
        </TabPanel>
        <TabPanel>
          <Reports />
        </TabPanel>
      </Tabs>
    </div>
  );
}

export default App;
