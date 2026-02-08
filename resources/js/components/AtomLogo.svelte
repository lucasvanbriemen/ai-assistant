<script>
  import { onMount } from 'svelte';
  import * as THREE from 'three';

  let { size = 200, animate = true } = $props();

  let container;
  let scene, camera, renderer;
  let nucleus, electrons = [];
  let orbits = [];
  let animationId;

  onMount(() => {
    initThreeJS();
    if (animate) {
      animateScene();
    }

    return () => {
      if (animationId) {
        cancelAnimationFrame(animationId);
      }
      if (renderer) {
        renderer.dispose();
      }
    };
  });

  function initThreeJS() {
    // Scene
    scene = new THREE.Scene();

    // Camera (zoomed out to show full glass sphere)
    camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
    camera.position.z = 12;

    // Renderer
    renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true
    });
    renderer.setSize(size, size);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    // Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);

    const pointLight = new THREE.PointLight(0x8b5cf6, 2, 100);
    pointLight.position.set(0, 0, 0);
    scene.add(pointLight);

    // Create nucleus (glassy glowing sphere at center)
    const nucleusGeometry = new THREE.SphereGeometry(0.5, 32, 32);
    const nucleusMaterial = new THREE.MeshPhongMaterial({
      color: 0x8b5cf6,
      emissive: 0x6366f1,
      emissiveIntensity: 0.8,
      shininess: 100,
      transparent: true,
      opacity: 0.6, // Glassy transparency
      specular: 0xffffff
    });
    nucleus = new THREE.Mesh(nucleusGeometry, nucleusMaterial);
    scene.add(nucleus);

    // Add outer glow to nucleus
    const glowGeometry = new THREE.SphereGeometry(0.7, 32, 32);
    const glowMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: 0.25
    });
    const glow = new THREE.Mesh(glowGeometry, glowMaterial);
    nucleus.add(glow);

    // Create 3 orbital paths evenly spaced
    const orbitRadius = 2.8;
    const tubeThickness = 0.05; // Thicker orbital lines

    const orbitConfigs = [
      { radius: orbitRadius, tubeRadius: tubeThickness, rotationX: 0, rotationY: 0, color: 0x8b5cf6, speed: 0.01 },
      { radius: orbitRadius, tubeRadius: tubeThickness, rotationX: Math.PI / 3, rotationY: 0, color: 0x6366f1, speed: 0.008 },
      { radius: orbitRadius, tubeRadius: tubeThickness, rotationX: (2 * Math.PI) / 3, rotationY: 0, color: 0x3b82f6, speed: 0.012 }
    ];

    orbitConfigs.forEach((config, index) => {
      // Create orbital ring (torus)
      const orbitGeometry = new THREE.TorusGeometry(config.radius, config.tubeRadius, 16, 100);
      const orbitMaterial = new THREE.MeshPhongMaterial({
        color: config.color,
        transparent: true,
        opacity: 0.4,
        emissive: config.color,
        emissiveIntensity: 0.3
      });
      const orbit = new THREE.Mesh(orbitGeometry, orbitMaterial);
      orbit.rotation.x = config.rotationX;
      orbit.rotation.y = config.rotationY;
      scene.add(orbit);

      // Create electron (3D sphere)
      const electronGeometry = new THREE.SphereGeometry(0.15, 16, 16);
      const electronMaterial = new THREE.MeshPhongMaterial({
        color: 0xffffff,
        emissive: config.color,
        emissiveIntensity: 0.8,
        shininess: 100
      });
      const electron = new THREE.Mesh(electronGeometry, electronMaterial);

      // Add glow to electron
      const electronGlowGeometry = new THREE.SphereGeometry(0.25, 16, 16);
      const electronGlowMaterial = new THREE.MeshBasicMaterial({
        color: config.color,
        transparent: true,
        opacity: 0.5
      });
      const electronGlow = new THREE.Mesh(electronGlowGeometry, electronGlowMaterial);
      electron.add(electronGlow);

      // Store electron with its orbit config
      electrons.push({
        mesh: electron,
        angle: (index * Math.PI * 2) / 3, // Spread electrons evenly
        radius: config.radius,
        speed: config.speed,
        rotationX: config.rotationX,
        rotationY: config.rotationY
      });

      scene.add(electron);
      orbits.push(orbit);
    });

    // Create outer glass sphere (Siri-style) - lighter and more subtle
    const glassGeometry = new THREE.SphereGeometry(3.5, 64, 64);
    const glassMaterial = new THREE.MeshPhysicalMaterial({
      color: 0xffffff,
      transparent: true,
      opacity: 0.1,
      roughness: 0.1,
      metalness: 0,
      transmission: 0.95, // High glass transparency
      thickness: 0.2,
      clearcoat: 1,
      clearcoatRoughness: 0.05,
      ior: 1.45, // Index of refraction (glass-like)
      reflectivity: 0.5,
      side: THREE.FrontSide,
      depthWrite: false // Prevent z-fighting with inner objects
    });
    const glassSphere = new THREE.Mesh(glassGeometry, glassMaterial);
    glassSphere.renderOrder = 999; // Render glass last
    scene.add(glassSphere);

    // Add subtle iridescent rim glow
    const rimGlowGeometry = new THREE.SphereGeometry(3.55, 32, 32);
    const rimGlowMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: 0.15,
      side: THREE.BackSide
    });
    const rimGlow = new THREE.Mesh(rimGlowGeometry, rimGlowMaterial);
    scene.add(rimGlow);

    // Initial render
    renderer.render(scene, camera);
  }

  function animateScene() {
    animationId = requestAnimationFrame(animateScene);

    // Rotate entire scene for 3D effect
    scene.rotation.y += 0.002;
    scene.rotation.x += 0.001;

    // Pulse nucleus
    const pulse = Math.sin(Date.now() * 0.002) * 0.05 + 1;
    nucleus.scale.set(pulse, pulse, pulse);

    // Animate electrons along their orbits
    electrons.forEach((electron) => {
      electron.angle += electron.speed;

      // Calculate position on orbital path
      const x = Math.cos(electron.angle) * electron.radius;
      const y = Math.sin(electron.angle) * electron.radius;

      // Apply orbital rotation to get 3D position
      const position = new THREE.Vector3(x, y, 0);
      position.applyEuler(new THREE.Euler(electron.rotationX, electron.rotationY, 0));

      electron.mesh.position.copy(position);

      // Pulse electron glow
      const electronPulse = Math.sin(Date.now() * 0.003 + electron.angle) * 0.1 + 1;
      if (electron.mesh.children[0]) {
        electron.mesh.children[0].scale.set(electronPulse, electronPulse, electronPulse);
      }
    });

    // Slowly rotate orbits
    orbits.forEach((orbit, index) => {
      orbit.rotation.z += 0.001 * (index + 1);
    });

    renderer.render(scene, camera);
  }
</script>

<div class="atom-container" bind:this={container} style="width: {size}px; height: {size}px;"></div>

<style>
  .atom-container {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }

  .atom-container :global(canvas) {
    display: block;
  }
</style>
