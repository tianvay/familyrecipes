/**
 * Rezeptauswahl für viele Rezepte: Suchfeld + „Nur meine Favoriten“ + antippbare Liste.
 * Ersetzt ein langes <select>; die Auswahl landet in einem versteckten Feld (recipe_id).
 */
export default ({ recipes, selected = null }) => ({
    recipes,
    query: '',
    selectedId: selected,
    // Mit Favoriten starten, wenn es welche gibt (und die Vorauswahl dazu passt)
    onlyFavorites: recipes.some((r) => r.favorite) && (selected === null || recipes.some((r) => r.id === selected && r.favorite)),

    get hasFavorites() {
        return this.recipes.some((recipe) => recipe.favorite);
    },

    get filtered() {
        const query = normalize(this.query);

        return this.recipes.filter((recipe) =>
            (!this.onlyFavorites || recipe.favorite) && (query === '' || normalize(recipe.title).includes(query)),
        );
    },

    get selected() {
        return this.recipes.find((recipe) => recipe.id === this.selectedId) ?? null;
    },

    select(recipe) {
        this.selectedId = recipe.id;
    },

    clear() {
        this.selectedId = null;
        this.$nextTick(() => this.$refs.search?.focus());
    },
});

/**
 * „Käse“ findet man auch mit „kase“, „Süßkartoffel“ mit „susskartoffel“.
 */
function normalize(text) {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/ß/g, 'ss')
        .toLowerCase()
        .trim();
}
