<script> 
  import * as THREE from 'three';

  const LOGO_PX_SIZE = 150;
  const LOGO_DIMETER = 6;

  let logoContainer = $state(null);
  let scene = $state(null);
  let camera = $state(null);
  let renderer = $state(null);

  function init() {
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = LOGO_DIMETER * 2;

    renderer = new THREE.WebGLRenderer({
      alpha: true,
      antialias: true
    });

    renderer.setSize(LOGO_PX_SIZE, LOGO_PX_SIZE);
    renderer.setPixelRatio(window.devicePixelRatio);

    logoContainer.appendChild(renderer.domElement);
  }
</script>

<div class="logo-container" bind:this={logoContainer}></div>