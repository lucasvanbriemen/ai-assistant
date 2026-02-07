<script>
    import { onMount } from 'svelte';
    import api from '../lib/api.js';

    let count = $state(0);
    let data = $state(null);
    let loading = $state(false);

    onMount(() => {
        console.log('Home page mounted');
    });

    function increment() {
        count += 1;
    }

    async function fetchData() {
        loading = true;
        try {
            // Example API call - replace with your endpoint
            // const result = await api.get('/api/example');
            // data = result;
            data = { message: 'API ready! Replace /api/example with your endpoint.' };
        } catch (error) {
            console.error('Error fetching data:', error);
            data = { error: error.message };
        }
        loading = false;
    }
</script>

<h1>Welcome to Your App</h1>
<p>This is the home page with API integration.</p>

<nav>
    <a href="/chat">Go to AI Chatbot</a>
</nav>

<button onclick={increment}>
    Clicked {count} times
</button>

<hr />

<h2>API Example</h2>
<button onclick={fetchData} disabled={loading}>
    {loading ? 'Loading...' : 'Fetch Data'}
</button>

{#if data}
    <pre>{JSON.stringify(data, null, 2)}</pre>
{/if}

<style>
    h1 {
        color: #333;
    }

    nav {
        margin: 2rem 0;
    }

    nav a {
        display: inline-block;
        background-color: #28a745;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: background-color 0.2s;
    }

    nav a:hover {
        background-color: #218838;
    }

    button {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
    }

    button:hover {
        background-color: #0056b3;
    }

    button:disabled {
        background-color: #ccc;
        cursor: not-allowed;
    }

    hr {
        margin: 2rem 0;
        border: none;
        border-top: 1px solid #ddd;
    }

    pre {
        background-color: #f5f5f5;
        padding: 1rem;
        border-radius: 4px;
        overflow-x: auto;
        font-size: 0.9rem;
    }
</style>
