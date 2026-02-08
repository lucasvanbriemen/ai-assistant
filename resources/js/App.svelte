<script>
    import { router, initRouter } from './stores/router.svelte.js';
    import api from './lib/api.js';
    import '@styles/App.scss';

    initRouter();
    window.api = api;
</script>


<main>
    {#if router.currentComponent}
        <router.currentComponent {...router.params} />
    {/if}
</main>
