<script>
    import AppHead from '@/components/AppHead.svelte';

    let decoder = new TextDecoder();
    let reader;
    let result = $state('');

    function click() {
        fetch('/api/test')
            .then((response) => {
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

<button onclick={click}>click me</button>
 <br>
output: {result}