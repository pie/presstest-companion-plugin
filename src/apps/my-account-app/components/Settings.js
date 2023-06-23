import React from 'react';

function Settings() {
    return (
        <div className='content-wrap'>
            <form>
                <fieldset>
                    <label for="new-domain">Add New Domain</label>
                    <input type="text" id="new-domain" name="new-domain" />
                    <button id="add-domain">Add</button>
                </fieldset>
                <fieldset>
                    <label for="domains">Select domain to test:</label>
                    <select name="domains" id="domains">
                        <option value="domain1">Domain1</option>
                        <option value="domain2">Domain2</option>
                    </select>
                    <button id="remove-domain">Remove</button>
                </fieldset>
                <input type="submit" value="Save" />
            </form>
        </div>
    );
};
export default Settings;