/**
 * Composer auto-resize and direction handling.
 * Grows the textarea to fit content (up to a cap) and applies LTR/RTL.
 */
import { elements } from '../core/dom.js';
import { applyDirection } from '../core/rtl.js';

/** Initialize the composer behavior. */
export function initComposer(){
  const { composer } = elements;
  function update(){
    applyDirection(composer, composer.value);
    composer.style.height = 'auto';
    const maxH = 200;
    composer.style.height = Math.min(maxH, composer.scrollHeight) + 'px';
  }
  composer.addEventListener('input', update);
  update();
}
