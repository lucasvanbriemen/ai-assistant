<script>
  import { onMount } from 'svelte';
  import * as THREE from 'three';

  let { size = 280, animate = true } = $props();

  // Animation speed controls (adjust these to change animation speeds)
  const ELECTRON_ORBIT_SPEED_MULTIPLIER = 2.0; // Speed of electrons moving along their orbits
  const SCENE_ROTATION_SPEED = 10.0; // Speed of entire atom/scene rotation
  const NUCLEUS_ROTATION_SPEED = 2.0; // Speed of nucleus particles rotating around each other

  let container;
  let scene, camera, renderer;
  let nucleusGroup; // Group to hold all nucleus particles
  let nucleusParticles = []; // Multiple nucleus particles
  let electrons = [];
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

    // Lighting - enhanced for glass sphere depth and brightness
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.8); // Much brighter ambient
    scene.add(ambientLight);

    // Main light from nucleus
    const nucleusLight = new THREE.PointLight(0x8b5cf6, 5, 100); // Increased intensity
    nucleusLight.position.set(0, 0, 0);
    scene.add(nucleusLight);

    // Add directional light for glass sphere highlights
    const dirLight = new THREE.DirectionalLight(0xffffff, 1.2); // Brighter directional
    dirLight.position.set(5, 5, 5);
    scene.add(dirLight);

    // Create a group to hold all nucleus particles (allows rotation around common center)
    nucleusGroup = new THREE.Group();
    scene.add(nucleusGroup);

    // Position nucleus particles in a 3D tetrahedral/pyramid arrangement
    const nucleusPositions = [
      { x: 0.35, y: 0.35, z: 0.35 },   // Top front right
      { x: -0.35, y: -0.35, z: 0.35 }, // Bottom front left
      { x: -0.35, y: 0.35, z: -0.35 }, // Top back left
      { x: 0.35, y: -0.35, z: -0.35 }  // Bottom back right (4th particle - pyramid base)
    ];

    const nucleusColors = [
      { color: 0x8b5cf6, emissive: 0x6366f1 },
      { color: 0x6366f1, emissive: 0x8b5cf6 },
      { color: 0x3b82f6, emissive: 0x6366f1 },
      { color: 0x8b5cf6, emissive: 0x3b82f6 }
    ];

    nucleusPositions.forEach((pos, index) => {
      // Create nucleus particle with highly reflective material
      const nucleusGeometry = new THREE.SphereGeometry(0.35, 64, 64); // Higher segments for better reflections
      const nucleusMaterial = new THREE.MeshPhongMaterial({
        color: nucleusColors[index].color,
        emissive: nucleusColors[index].emissive,
        emissiveIntensity: 0.4, // Very low to see reflections better
        shininess: 300, // Extremely high shininess
        transparent: true,
        opacity: 0.95,
        specular: 0xffffff,
        reflectivity: 1.0
      });
      const nucleusParticle = new THREE.Mesh(nucleusGeometry, nucleusMaterial);

      // Position in 3D space
      nucleusParticle.position.set(pos.x, pos.y, pos.z);

      // Add outer glow to each particle
      const glowGeometry = new THREE.SphereGeometry(0.5, 32, 32);
      const glowMaterial = new THREE.MeshBasicMaterial({
        color: nucleusColors[index].color,
        transparent: true,
        opacity: 0.4
      });
      const glow = new THREE.Mesh(glowGeometry, glowMaterial);
      nucleusParticle.add(glow);

      // Add to group instead of scene
      nucleusGroup.add(nucleusParticle);
      nucleusParticles.push({ mesh: nucleusParticle });
    });

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

      // Very strong point light to create reflections on nucleus
      const electronLight = new THREE.PointLight(config.color, 8.0, 15); // Much stronger
      electronLight.decay = 1.0; // Less decay for longer range
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
    scene.rotation.y += 0.002 * SCENE_ROTATION_SPEED;
    scene.rotation.x += 0.001 * SCENE_ROTATION_SPEED;

    // Rotate the entire nucleus group so particles swap positions in 3D
    nucleusGroup.rotation.x += 0.008 * NUCLEUS_ROTATION_SPEED;
    nucleusGroup.rotation.y += 0.012 * NUCLEUS_ROTATION_SPEED;
    nucleusGroup.rotation.z += 0.006 * NUCLEUS_ROTATION_SPEED;

    // Subtle pulse for each nucleus particle
    nucleusParticles.forEach((particle, index) => {
      const pulse = Math.sin(Date.now() * 0.002 + index) * 0.06 + 1;
      particle.mesh.scale.set(pulse, pulse, pulse);
    });

    // Animate electrons along their orbits (position only - no scaling/pulsing)
    electrons.forEach((electron) => {
      electron.angle += electron.speed * ELECTRON_ORBIT_SPEED_MULTIPLIER;

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
      orbit.rotation.z += 0.001 * (index + 1) * SCENE_ROTATION_SPEED;
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
