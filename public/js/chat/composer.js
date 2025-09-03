import { elements } from './dom.js';
import { applyDirection } from './rtl.js';

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

