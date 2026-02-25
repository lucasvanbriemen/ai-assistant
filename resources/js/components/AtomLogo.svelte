<script>
  import * as THREE from 'three';
  import '@styles/AtomLogo.scss';

  import { untrack } from 'svelte';

  let { size = 280, animate = true, thinking = false } = $props();

  // Normal state speeds
  const NORMAL_ELECTRON_SPEED = 2.0;
  const NORMAL_SCENE_SPEED = 25.0;
  const NORMAL_NUCLEUS_SPEED = 5.0;
  const NORMAL_PULSE_AMPLITUDE = 0.06;
  const NORMAL_EMISSIVE_INTENSITY = 0.4;
  const NORMAL_ELECTRON_LIGHT_INTENSITY = 125.0;
  const NORMAL_RIM_OPACITY = 0.1;

  // Thinking state speeds
  const THINKING_ELECTRON_SPEED = 6.0;
  const THINKING_SCENE_SPEED = 50.0;
  const THINKING_NUCLEUS_SPEED = 12.0;
  const THINKING_PULSE_AMPLITUDE = 0.15;
  const THINKING_EMISSIVE_INTENSITY = 0.8;
  const THINKING_ELECTRON_LIGHT_INTENSITY = 250.0;
  const THINKING_RIM_OPACITY = 0.25;

  // Interpolated values (smoothly transition between states)
  let currentElectronSpeed = NORMAL_ELECTRON_SPEED;
  let currentSceneSpeed = NORMAL_SCENE_SPEED;
  let currentNucleusSpeed = NORMAL_NUCLEUS_SPEED;
  let currentPulseAmplitude = NORMAL_PULSE_AMPLITUDE;
  let currentEmissiveIntensity = NORMAL_EMISSIVE_INTENSITY;
  let currentElectronLightIntensity = NORMAL_ELECTRON_LIGHT_INTENSITY;
  let currentRimOpacity = NORMAL_RIM_OPACITY;
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
  let rimMesh; // Reference to rim glow for dynamic opacity

  $effect(() => {
    // Only depend on container and animate — NOT thinking
    const el = container;
    const shouldAnimate = animate;

    if (el) {
      untrack(() => {
        initThreeJS();
        if (shouldAnimate) {
          animateScene();
        }
      });
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
      opacity: currentRimOpacity,
      side: THREE.BackSide
    });
    rimMesh = new THREE.Mesh(rimGeometry, rimMaterial);
    scene.add(rimMesh);

    // Initial render
    renderer.render(scene, camera);
  }

  function lerp(current, target, speed) {
    return current + (target - current) * speed;
  }

  function animateScene() {
    animationId = requestAnimationFrame(animateScene);

    // Smoothly interpolate all values toward target state
    const lerpSpeed = 0.03;
    const targetElectronSpeed = thinking ? THINKING_ELECTRON_SPEED : NORMAL_ELECTRON_SPEED;
    const targetSceneSpeed = thinking ? THINKING_SCENE_SPEED : NORMAL_SCENE_SPEED;
    const targetNucleusSpeed = thinking ? THINKING_NUCLEUS_SPEED : NORMAL_NUCLEUS_SPEED;
    const targetPulseAmplitude = thinking ? THINKING_PULSE_AMPLITUDE : NORMAL_PULSE_AMPLITUDE;
    const targetEmissiveIntensity = thinking ? THINKING_EMISSIVE_INTENSITY : NORMAL_EMISSIVE_INTENSITY;
    const targetElectronLightIntensity = thinking ? THINKING_ELECTRON_LIGHT_INTENSITY : NORMAL_ELECTRON_LIGHT_INTENSITY;
    const targetRimOpacity = thinking ? THINKING_RIM_OPACITY : NORMAL_RIM_OPACITY;

    currentElectronSpeed = lerp(currentElectronSpeed, targetElectronSpeed, lerpSpeed);
    currentSceneSpeed = lerp(currentSceneSpeed, targetSceneSpeed, lerpSpeed);
    currentNucleusSpeed = lerp(currentNucleusSpeed, targetNucleusSpeed, lerpSpeed);
    currentPulseAmplitude = lerp(currentPulseAmplitude, targetPulseAmplitude, lerpSpeed);
    currentEmissiveIntensity = lerp(currentEmissiveIntensity, targetEmissiveIntensity, lerpSpeed);
    currentElectronLightIntensity = lerp(currentElectronLightIntensity, targetElectronLightIntensity, lerpSpeed);
    currentRimOpacity = lerp(currentRimOpacity, targetRimOpacity, lerpSpeed);

    // Rotate entire scene for 3D effect
    scene.rotation.y += 0.002 * currentSceneSpeed;
    scene.rotation.x += 0.001 * currentSceneSpeed;

    // Rotate the entire nucleus group so particles swap positions in 3D
    nucleusGroup.rotation.x += 0.008 * currentNucleusSpeed;
    nucleusGroup.rotation.y += 0.012 * currentNucleusSpeed;
    nucleusGroup.rotation.z += 0.006 * currentNucleusSpeed;

    // Pulse for each nucleus particle (amplitude changes with thinking state)
    nucleusParticles.forEach((particle, index) => {
      const pulse = Math.sin(Date.now() * 0.002 + index) * currentPulseAmplitude + 1;
      particle.mesh.scale.set(pulse, pulse, pulse);

      // Update emissive intensity
      particle.mesh.material.emissiveIntensity = currentEmissiveIntensity;
    });

    // Animate electrons along their orbits
    electrons.forEach((electron) => {
      electron.angle += electron.speed * currentElectronSpeed;

      // Calculate position on orbital path
      const x = Math.cos(electron.angle) * electron.radius;
      const y = Math.sin(electron.angle) * electron.radius;

      // Apply orbital rotation to get 3D position
      const position = new THREE.Vector3(x, y, 0);
      position.applyEuler(new THREE.Euler(electron.rotationX, electron.rotationY, 0));

      electron.mesh.position.copy(position);

      // Update electron light intensity
      electron.mesh.children[0].intensity = currentElectronLightIntensity;
    });

    // Slowly rotate orbits
    orbits.forEach((orbit, index) => {
      orbit.rotation.z += 0.001 * (index + 1) * currentSceneSpeed;
    });

    // Update rim glow opacity
    if (rimMesh) {
      rimMesh.material.opacity = currentRimOpacity;
    }

    renderer.render(scene, camera);
  }
</script>

<div class="atom-container" bind:this={container} style="width: {size}px; height: {size}px;"></div>
