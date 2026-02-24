<script>
    import AppHead from '@/components/AppHead.svelte';

    let result = $state('');

    function click() {
        fetch('/api/test')
            .then((response) => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                function read() {
                    reader.read().then(({ done, value }) => {
                        if (done) {
                            console.log('Stream complete:', result);
                            return;
                        }
                        result += decoder.decode(value, { stream: true });
                        console.log('Received chunk:', result);
                        read();
                    });
                }

                read();
            })
            .catch((error) => {
                console.error('Error fetching stream:', error);
            });
    }
</script>

<AppHead title="Home">
</AppHead>

<button onclick={click}>click me</button>
 <br>
output: {result}