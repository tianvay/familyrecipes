import './bootstrap';
import Alpine from 'alpinejs';
import shoppingList from './shopping-list';

Alpine.data('shoppingList', shoppingList);

window.Alpine = Alpine;
Alpine.start();
