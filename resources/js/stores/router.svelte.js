import Home from '../pages/Home.svelte';
import page from 'page';

const routes = {
    '/': Home,
};

class Router {
    currentComponent = $state(Home);
    params = $state({});
}

export const router = new Router();

export function initRouter() {
    page('/', () => {
        router.currentComponent = routes['/'];
        router.params = {};
    });

    page('*', () => {
        page.redirect('/');
    });

    page.start();
}
