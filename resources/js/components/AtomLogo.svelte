<script>
  import { onMount } from 'svelte';
  import * as THREE from 'three';

  let { size = 280, animate = true } = $props();

  let container;
  let scene, camera, renderer;
  let nucleus, electrons = [];
  let orbits = [];
  let highlights = []; // Glass reflection highlights
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

    // Lighting - enhanced for glass sphere depth
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.3);
    scene.add(ambientLight);

    // Main light from nucleus
    const nucleusLight = new THREE.PointLight(0x8b5cf6, 3, 100);
    nucleusLight.position.set(0, 0, 0);
    scene.add(nucleusLight);

    // Add directional light for glass sphere highlights
    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(5, 5, 5);
    scene.add(dirLight);

    // Create nucleus (bigger, more reflective to show electron lights)
    const nucleusGeometry = new THREE.SphereGeometry(0.6, 32, 32); // Bigger
    const nucleusMaterial = new THREE.MeshPhongMaterial({
      color: 0x8b5cf6,
      emissive: 0x6366f1,
      emissiveIntensity: 1.4, // Even brighter
      shininess: 150, // More reflective
      transparent: true,
      opacity: 0.8,
      specular: 0xffffff
    });
    nucleus = new THREE.Mesh(nucleusGeometry, nucleusMaterial);
    scene.add(nucleus);

    // Add outer glow to nucleus (brighter and bigger)
    const glowGeometry = new THREE.SphereGeometry(0.85, 32, 32);
    const glowMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: 0.5 // Brighter glow
    });
    const glow = new THREE.Mesh(glowGeometry, glowMaterial);
    nucleus.add(glow);

    // Create 3 orbital paths evenly spaced (bigger atom)
    const orbitRadius = 3.2; // Increased from 2.8
    const tubeThickness = 0.06; // Thicker orbital lines

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

      // Create electron (completely static, solid sphere)
      const electronGeometry = new THREE.SphereGeometry(0.18, 32, 32); // Slightly bigger
      const electronMaterial = new THREE.MeshBasicMaterial({
        color: config.color
      });
      const electron = new THREE.Mesh(electronGeometry, electronMaterial);

      // Stronger point light to better illuminate the nucleus
      const electronLight = new THREE.PointLight(config.color, 3.0, 10);
      electronLight.decay = 1.5;
      electron.add(electronLight);

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

    // Create outer glass sphere with proper reflections (Siri-style)
    const glassGeometry = new THREE.SphereGeometry(3.5, 128, 128);
    const glassMaterial = new THREE.MeshPhongMaterial({
      color: 0xffffff,
      transparent: true,
      opacity: 0.12,
      shininess: 150, // High shininess for sharp reflections
      specular: 0xffffff, // White specular highlights
      side: THREE.DoubleSide,
      depthWrite: false
    });
    const glassSphere = new THREE.Mesh(glassGeometry, glassMaterial);
    glassSphere.renderOrder = 999;
    scene.add(glassSphere);

    // Add flat circular sprites for glass reflections (not spheres - won't stick out)
    electrons.forEach((electronData, index) => {
      const highlightGeometry = new THREE.CircleGeometry(0.8, 32); // Flat circle, bigger than electrons
      const highlightMaterial = new THREE.MeshBasicMaterial({
        color: orbitConfigs[index].color,
        transparent: true,
        opacity: 0,
        blending: THREE.AdditiveBlending,
        side: THREE.DoubleSide
      });
      const highlight = new THREE.Mesh(highlightGeometry, highlightMaterial);
      highlights.push({ mesh: highlight, electronData });
      scene.add(highlight);
    });

    // Subtle rim glow on glass edge
    const rimGeometry = new THREE.SphereGeometry(3.58, 64, 64);
    const rimMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: 0.1,
      side: THREE.BackSide
    });
    const rim = new THREE.Mesh(rimGeometry, rimMaterial);
    scene.add(rim);

    // Initial render
    renderer.render(scene, camera);
  }

  function animateScene() {
    animationId = requestAnimationFrame(animateScene);

    // Rotate entire scene for 3D effect
    scene.rotation.y += 0.002;
    scene.rotation.x += 0.001;

    // Pulse nucleus only (subtle)
    const pulse = Math.sin(Date.now() * 0.002) * 0.05 + 1;
    nucleus.scale.set(pulse, pulse, pulse);

    // Animate electrons along their orbits (position only - no scaling/pulsing)
    electrons.forEach((electron) => {
      electron.angle += electron.speed;

      // Calculate position on orbital path
      const x = Math.cos(electron.angle) * electron.radius;
      const y = Math.sin(electron.angle) * electron.radius;

      // Apply orbital rotation to get 3D position
      const position = new THREE.Vector3(x, y, 0);
      position.applyEuler(new THREE.Euler(electron.rotationX, electron.rotationY, 0));

      electron.mesh.position.copy(position);

      // Electrons remain completely static - only position changes, no scaling or pulsing
    });

    // Slowly rotate orbits
    orbits.forEach((orbit, index) => {
      orbit.rotation.z += 0.001 * (index + 1);
    });

    // Update glass reflection highlights (flat circles on glass surface)
    highlights.forEach((highlight) => {
      const electronPos = highlight.electronData.mesh.position;

      // Position exactly on inner surface of glass sphere
      const direction = electronPos.clone().normalize();
      const glassReflectionPos = direction.multiplyScalar(3.45); // Just inside glass surface (3.5)

      highlight.mesh.position.copy(glassReflectionPos);

      // Orient the flat circle to face outward from center (tangent to sphere)
      highlight.mesh.lookAt(new THREE.Vector3(0, 0, 0));
      highlight.mesh.rotateX(Math.PI); // Flip to face outward

      // Calculate opacity based on electron distance from glass
      const distance = electronPos.length();
      const glassRadius = 3.5;
      const distanceToGlass = Math.abs(distance - glassRadius);

      // Fade in when electron is close to glass (bigger and brighter reflections)
      const maxDistance = 2.0;
      const opacity = Math.max(0, 1 - (distanceToGlass / maxDistance)) * 0.6; // Even brighter
      highlight.mesh.material.opacity = opacity;
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
