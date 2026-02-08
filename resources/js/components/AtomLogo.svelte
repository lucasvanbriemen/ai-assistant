<script>
  import { onMount } from 'svelte';
  import * as THREE from 'three';

  let { size = 280, animate = true } = $props();

  const ELECTRON_ORBIT_SPEED_MULTIPLIER = 2.0;
  const SCENE_ROTATION_SPEED = 25.0;
  const NUCLEUS_ROTATION_SPEED = 5.0;
  const ORBIT_RADIUS = 3.2;
  const ORBIT_TUBE_THICKNESS = 0.06;
  const ORBIT_CONFIGS = [
    { radius: ORBIT_RADIUS, tubeRadius: ORBIT_TUBE_THICKNESS, rotationX: 0, rotationY: 0, color: 0x8b5cf6, speed: 0.01 },
    { radius: ORBIT_RADIUS, tubeRadius: ORBIT_TUBE_THICKNESS, rotationX: (1 * Math.PI) / 4, rotationY: 0, color: 0x6366f1, speed: 0.012 },
    { radius: ORBIT_RADIUS, tubeRadius: ORBIT_TUBE_THICKNESS, rotationX: (2 * Math.PI) / 4, rotationY: 0, color: 0x3b82f6, speed: 0.012 },
    { radius: ORBIT_RADIUS, tubeRadius: ORBIT_TUBE_THICKNESS, rotationX: (3 * Math.PI) / 4, rotationY: 0, color: 0x3b82f6, speed: 0.012 },
  ];

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

    // Lighting - ambient only, no nucleus/directional lights to avoid unwanted glass reflections
    const ambientLight = new THREE.AmbientLight(0xffffff, 1.0); // Ambient light for general visibility
    scene.add(ambientLight);

    // No nucleus light or directional light - only electron lights should reflect on glass

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

    // Create orbital paths and electrons from config
    ORBIT_CONFIGS.forEach((config, index) => {
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

      // Strong point light to create reflections on glass and nucleus
      const electronLight = new THREE.PointLight(config.color, 125.0, 120); // Stronger for glass reflections
      electronLight.decay = 0.8; // Lower decay for wider reach to glass
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

    // Create outer glass sphere with visible reflections (no bloom, no jumping)
    const glassGeometry = new THREE.SphereGeometry(3.5, 128, 128);
    const glassMaterial = new THREE.MeshPhysicalMaterial({
      color: 0xe0e0e0,       // Darker tint for better reflection visibility
      transparent: true,
      opacity: 0.28,         // Darker container for visible reflections
      transmission: 0.75,    // Less transparent - darker surface
      thickness: 0.4,        // Moderate glass thickness
      roughness: 0.0,        // Clear glass (not cloudy)
      metalness: 0.0,
      clearcoat: 0.0,        // No clearcoat to avoid jumping
      clearcoatRoughness: 0.0,
      ior: 1.5,              // Glass index of refraction
      reflectivity: 0.6,     // Higher reflectivity for visible electron lights
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
