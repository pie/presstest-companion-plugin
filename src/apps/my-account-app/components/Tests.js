import React from 'react';

function Tests() {
    return (
        <div className='content-wrap'>
            <form>
                <fieldset>
                    <input type="checkbox" id="test1" name="test1" value="Test 1" />
                    <label for="test1">Test 1</label>
                    <input type="checkbox" id="test2" name="test2" value="Test 2" />
                    <label for="test2">Test 2</label>
                    <input type="checkbox" id="test3" name="test3" value="Test 3" />
                    <label for="test3">Test 3</label>
                </fieldset>
                <input type="submit" value="Run Tests" />
            </form>
        </div>
    );
};
export default Tests;