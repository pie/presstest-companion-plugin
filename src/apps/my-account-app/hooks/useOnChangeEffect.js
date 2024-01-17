// Wrapper for useEffect that doesn't run on load, only when states are changed (used for posting to db when a user changes options)
import { useEffect, useRef } from 'react';

const useOnChangeEffect = (func, deps) => {
    const didMount = useRef(false);
    useEffect(() => {
        if ( didMount.current ) {
            func();
        } else {
            didMount.current = true;
        }
    }, deps);
}

export default useOnChangeEffect;