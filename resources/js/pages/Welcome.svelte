<script>
    import AppHead from '@/components/AppHead.svelte';

    let prompt = $state('Explain the theory of relativity in 1 paragraph.');

    let decoder = new TextDecoder();
    let reader;
    let result = $state('');

    function click() {
        fetch('/api/test', {
            method: 'POST',
            body: JSON.stringify({ prompt }),
            headers: {'Content-Type': 'application/json',},
        }).then((response) => {
                reader = response.body.getReader();
                read();
            })
    }

    function read() {
        reader.read().then(({ done, value }) => {
            if (done) { return; }
            result += decoder.decode(value, { stream: true });
            read();
        });
    }
</script>

<AppHead title="Home">
</AppHead>

<input type="text" placeholder="Type something..." bind:value={prompt} />
<button onclick={click}>submit</button>
<br>
output: {result}