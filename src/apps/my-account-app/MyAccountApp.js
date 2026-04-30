import React from 'react';
import { Tab, Tabs, TabList, TabPanel } from 'react-tabs';
import Testing from './components/Testing';
import Reports from './components/Reports';
import Settings from './components/Settings';
import '../../../node_modules/react-tabs/style/react-tabs.scss';
import '../../sass/apps/my-account-app.scss';

function App() {
  return (
    <div id='presstest-app'>
      <Tabs>
        <TabList>
          <Tab>Run Tests</Tab>
          <Tab>Reports</Tab>
          <Tab>Settings</Tab>
        </TabList>

        {/* forceRender keeps Testing state alive when switching tabs */}
        <TabPanel forceRender>
          <Testing />
        </TabPanel>
        <TabPanel>
          <Reports />
        </TabPanel>
        <TabPanel>
          <Settings />
        </TabPanel>
      </Tabs>
    </div>
  );
}

export default App;
