import React from 'react';
import { Tab, Tabs, TabList, TabPanel } from 'react-tabs';
import Settings from './components/Settings';
import Tests from './components/Tests';
import Reports from './components/Reports';
import '../../../node_modules/react-tabs/style/react-tabs.scss';

function App() {
  return (
    <div>
      <h2>Testing Platform</h2>
      <Tabs>
        <TabList>
          <Tab>Settings</Tab>
          <Tab>Tests</Tab>
          <Tab>Reports</Tab>
        </TabList>

        <TabPanel>
          <Settings />
        </TabPanel>
        <TabPanel>
          <Tests />
        </TabPanel>
        <TabPanel>
          <Reports />
        </TabPanel>
      </Tabs>
    </div>
  );
}

export default App;
