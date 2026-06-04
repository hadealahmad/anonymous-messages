/**
 * Block Icon
 * 
 * SVG icon for the Anonymous Messages block.
 * Uses an envelope/mail icon to represent anonymous messaging.
 */

import { createElement } from '@wordpress/element';

const blockIcon = createElement(
    'svg',
    {
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        width: 24,
        height: 24,
        fill: 'currentColor',
    },
    createElement('path', {
        d: 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z',
    }),
    createElement('circle', {
        cx: '18',
        cy: '5',
        r: '3',
        fill: '#007cba',
        opacity: '0.8',
    })
);

export default blockIcon;
