import './bootstrap';
import Alpine from 'alpinejs';
import cookingMode from './cooking-mode';
import recipeAppearance from './recipe-appearance';
import recipePicker from './recipe-picker';
import shoppingList from './shopping-list';

Alpine.data('cookingMode', cookingMode);
Alpine.data('recipeAppearance', recipeAppearance);
Alpine.data('recipePicker', recipePicker);
Alpine.data('shoppingList', shoppingList);

window.Alpine = Alpine;
Alpine.start();
