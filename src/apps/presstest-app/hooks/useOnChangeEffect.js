import { useEffect, useRef } from 'react';

/**
 * Like useEffect, but skips the initial mount.
 *
 * The callback only fires when the dependencies change after the component
 * has already rendered once — useful for persisting state to the server
 * without triggering a save on load.
 *
 * @param {Function} func Callback to invoke on dependency change.
 * @param {Array}    deps Dependency array passed to the underlying useEffect.
 */
const useOnChangeEffect = ( func, deps ) => {
    const didMount = useRef(false);

    useEffect(() => {
        if (didMount.current) func();
        else didMount.current = true;
    }, deps);
}

export default useOnChangeEffect;