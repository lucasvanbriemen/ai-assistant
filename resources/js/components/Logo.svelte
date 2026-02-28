<script>
  import * as THREE from 'three';
  import logo from '@/lib/logo.js';

  import { onMount } from 'svelte';

  let { size = 280, state = "thinking" } = $props();

  const ALLOWED_STATES = ["normal", "thinking"];

  const ELECTRON_SPEEDS = { normal: 2.0, thinking: 4.0 };
  const SCENE_SPEEDS = { normal: 15.0, thinking: 35.0 };
  const NUCLEUS_SPEEDS = { normal: 2.5, thinking: 5.0 };
  const PULSE_AMPLITUDES = { normal: 0.06, thinking: 0.15 };
  const EMISSIVE_INTENSITIES = { normal: 0, thinking: 0.8 };
  const ELECTRON_LIGHT_INTENSITIES = { normal: 125.0, thinking: 150.0 };
  const OVERLAY_OPACITIES = { normal: 0.15, thinking: 0.25 };
  const DISPLACEMENT_AMPS = { normal: 0.025, thinking: 0.15 };
  const NUCLEUS_JITTERS = { normal: 0, thinking: 0.5 };
  const RIM_DISPLACEMENT_AMPS = { normal: 0, thinking: 0.2 };
  const NUCLEUS_DEFORM_AMPS = { normal: 0, thinking: 0.06 };

  const ORBIT_RADIUS = 3.25;
  const ORBIT_TUBE_THICKNESS = 0.05;
  const ORBIT_CONFIGS = [
    { radius: ORBIT_RADIUS, color: 0x8b5cf6, speed: 0.012 },
    { radius: ORBIT_RADIUS, color: 0x6366f1, speed: 0.012 },
    { radius: ORBIT_RADIUS, color: 0x3b82f6, speed: 0.012 },
    { radius: ORBIT_RADIUS, color: 0x3b82f6, speed: 0.012 },
  ];

  const NUCLEUS_CONFIG = [
    { color: 0x8b5cf6, x: 0.35, y: 0.35, z: 0.35 },
    { color: 0x6366f1, x: -0.35, y: -0.35, z: 0.35 },
    { color: 0x3b82f6, x: -0.35, y: 0.35, z: -0.35 },
    { color: 0x8b5cf6, x: 0.35, y: -0.35, z: -0.35 }
  ]

  let container;
  let scene, camera, renderer;
  let nucleusGroup; // Group to hold all nucleus particles
  let nucleusParticles = []; // Multiple nucleus particles
  let electrons = [];
  let orbits = [];
  let rimMesh; // Reference to rim glow for dynamic opacity
  let glassSphere; // Reference to glass sphere for displacement
  let originalPositions = null; // Store original vertex positions
  let originalRimPositions = null; // Store original rim vertex positions
  let originalNucleusPositions = []; // Store original nucleus vertex positions (one per particle)
  let currentDisplacementAmp = 0; // Current displacement amplitude (lerped)
  let currentNucleusJitter = 0; // Current nucleus jitter amplitude (lerped)
  let currentRimDisplacementAmp = 0; // Current rim displacement amplitude (lerped)
  let currentNucleusDeformAmp = 0; // Current nucleus deform amplitude (lerped)
  let currentElectronSpeed = ELECTRON_SPEEDS[state];
  let currentSceneSpeed = SCENE_SPEEDS[state];
  let currentNucleusSpeed = NUCLEUS_SPEEDS[state];
  let currentPulseAmplitude = PULSE_AMPLITUDES[state];
  let currentEmissiveIntensity = EMISSIVE_INTENSITIES[state];
  let currentElectronLightIntensity = ELECTRON_LIGHT_INTENSITIES[state];
  let currentOverlayOpacity = OVERLAY_OPACITIES[state];

  function init() {
    if (!ALLOWED_STATES.includes(state)) {
      return console.error(`Invalid state "${state}" for Logo component. Allowed states: ${ALLOWED_STATES.join(", ")}`);
    }

    initThreeJS();
    animateScene();
  }
  
  function initThreeJS() {
    initScene();
    initNucleusParticles();
    initOrbitsAndElectrons();
    initGlassSphere();
  }

  function initGlassSphere() {
    const glassGeometry = new THREE.SphereGeometry(3.5, 64, 64);
    const glassMaterial = new THREE.MeshPhysicalMaterial({
      transparent: true,
      opacity: 0.28,
      transmission: 0.1,
      thickness: 1.25,
      roughness: 0.0,
      metalness: 1.0,
      side: THREE.DoubleSide,
      depthWrite: false
    });
    glassSphere = new THREE.Mesh(glassGeometry, glassMaterial);
    glassSphere.renderOrder = 999;
    scene.add(glassSphere);

    originalPositions = new Float32Array(glassGeometry.attributes.position.array);

    // Subtle rim glow on glass edge
    const rimGeometry = new THREE.SphereGeometry(3.75, 64, 64);
    const rimMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: currentOverlayOpacity,
      side: THREE.BackSide
    });
    rimMesh = new THREE.Mesh(rimGeometry, rimMaterial);
    scene.add(rimMesh);

    originalRimPositions = new Float32Array(rimGeometry.attributes.position.array);
  }

  function initScene() {
    scene = new THREE.Scene();

    camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
    camera.position.z = 12;

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(size, size);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    renderer.render(scene, camera);
  }

  function initNucleusParticles() {
    nucleusGroup = new THREE.Group();
    scene.add(nucleusGroup);

    NUCLEUS_CONFIG.forEach((config, index) => {
      const nucleusGeometry = new THREE.SphereGeometry(0.35, 32, 32);
      const nucleusMaterial = new THREE.MeshPhongMaterial({
        color: config.color,
        shininess: 100,
        transparent: true,
        opacity: 0.95,
        specular: 0xffffff,
        reflectivity: 1.0
      });
      const nucleusParticle = new THREE.Mesh(nucleusGeometry, nucleusMaterial);

      nucleusParticle.position.set(config.x, config.y, config.z);

      // Add outer glow to each particle
      const glowGeometry = new THREE.SphereGeometry(0.5, 32, 32);
      const glowMaterial = new THREE.MeshBasicMaterial({
        color: config.color,
        transparent: true,
        opacity: 0.4
      });
      const glow = new THREE.Mesh(glowGeometry, glowMaterial);
      nucleusParticle.add(glow);

      nucleusGroup.add(nucleusParticle);

      const origNucPos = new Float32Array(nucleusGeometry.attributes.position.array);
      originalNucleusPositions.push(origNucPos);

      nucleusParticles.push({
        mesh: nucleusParticle,
        basePos: { x: config.x, y: config.y, z: config.z },
        phase: index * 1.7
      });
    });
  }

  function initOrbitsAndElectrons() {
    ORBIT_CONFIGS.forEach((config, index) => {
      // Create orbital ring (torus)
      const orbitGeometry = new THREE.TorusGeometry(config.radius, ORBIT_TUBE_THICKNESS, 16, 100);
      const orbitMaterial = new THREE.MeshPhongMaterial({
        color: config.color,
        transparent: true,
        opacity: 0.4,
        emissive: config.color,
        emissiveIntensity: 0.3
      });
      const orbit = new THREE.Mesh(orbitGeometry, orbitMaterial);
      orbit.rotation.x = index * Math.PI / ORBIT_CONFIGS.length;
      orbit.rotation.y = 0;
      scene.add(orbit);

      // Create electron (completely static, solid sphere)
      const electronGeometry = new THREE.SphereGeometry(ORBIT_TUBE_THICKNESS + 0.15, 132, 132);
      const electronMaterial = new THREE.MeshBasicMaterial({ color: config.color });
      const electron = new THREE.Mesh(electronGeometry, electronMaterial);

      const electronLight = new THREE.PointLight(config.color, 125.0, 120);
      electronLight.decay = 0.8;
      electron.add(electronLight);

      electrons.push({
        mesh: electron,
        angle: (index * Math.PI * 2) / ORBIT_CONFIGS.length,
        radius: config.radius,
        speed: config.speed,
        rotationX: index * Math.PI / ORBIT_CONFIGS.length,
        rotationY: 0
      });

      scene.add(electron);
      orbits.push(orbit);
    });
  }

  function animateScene() {
    requestAnimationFrame(animateScene);

    // Smoothly interpolate all values toward target state
    const lerpSpeed = 0.03;
    const targetElectronSpeed = ELECTRON_SPEEDS[state];
    const targetSceneSpeed = SCENE_SPEEDS[state];
    const targetNucleusSpeed = NUCLEUS_SPEEDS[state];
    const targetPulseAmplitude = PULSE_AMPLITUDES[state];
    const targetEmissiveIntensity = EMISSIVE_INTENSITIES[state];
    const targetElectronLightIntensity = ELECTRON_LIGHT_INTENSITIES[state];
    const targetOverlayOpacity = OVERLAY_OPACITIES[state];

    const targetDisplacementAmp = DISPLACEMENT_AMPS[state];
    const targetNucleusJitter = NUCLEUS_JITTERS[state];
    const targetRimDisplacementAmp = RIM_DISPLACEMENT_AMPS[state];
    const targetNucleusDeformAmp = NUCLEUS_DEFORM_AMPS[state];

    currentElectronSpeed = THREE.MathUtils.lerp(currentElectronSpeed, targetElectronSpeed, lerpSpeed);
    currentSceneSpeed = THREE.MathUtils.lerp(currentSceneSpeed, targetSceneSpeed, lerpSpeed);
    currentNucleusSpeed = THREE.MathUtils.lerp(currentNucleusSpeed, targetNucleusSpeed, lerpSpeed);
    currentPulseAmplitude = THREE.MathUtils.lerp(currentPulseAmplitude, targetPulseAmplitude, lerpSpeed);
    currentEmissiveIntensity = THREE.MathUtils.lerp(currentEmissiveIntensity, targetEmissiveIntensity, lerpSpeed);
    currentElectronLightIntensity = THREE.MathUtils.lerp(currentElectronLightIntensity, targetElectronLightIntensity, lerpSpeed);
    currentOverlayOpacity = THREE.MathUtils.lerp(currentOverlayOpacity, targetOverlayOpacity, lerpSpeed);
    currentDisplacementAmp = THREE.MathUtils.lerp(currentDisplacementAmp, targetDisplacementAmp, lerpSpeed);
    currentNucleusJitter = THREE.MathUtils.lerp(currentNucleusJitter, targetNucleusJitter, lerpSpeed);
    currentRimDisplacementAmp = THREE.MathUtils.lerp(currentRimDisplacementAmp, targetRimDisplacementAmp, lerpSpeed);
    currentNucleusDeformAmp = THREE.MathUtils.lerp(currentNucleusDeformAmp, targetNucleusDeformAmp, lerpSpeed);

    // Rotate entire scene for 3D effect
    scene.rotation.y += 0.002 * currentSceneSpeed;
    scene.rotation.x += 0.002 * currentSceneSpeed / 2;  // We need to slow 1 axis rotation to prevent excessive spinning when thinking in 8's

    applyNoise(glassSphere, Date.now() * 0.001, currentDisplacementAmp, originalPositions);
    applyNoise(rimMesh, Date.now() * 0.001 * 0.7, currentDisplacementAmp, originalRimPositions);

    animateNucleus();
    animateOrbits();
    animateElectrons();

    rimMesh.material.opacity = currentOverlayOpacity;

    renderer.render(scene, camera);
  }

  function animateOrbits() {
    orbits.forEach((orbit, index) => {
      orbit.rotation.z += 0.001 * (index + 1) * currentSceneSpeed;
    });
  }

  function animateElectrons() {
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
  }

  function animateNucleus() {
    // Rotate the entire nucleus group so particles swap positions in 3D
    nucleusGroup.rotation.x += 0.008 * currentNucleusSpeed;
    nucleusGroup.rotation.y += 0.012 * currentNucleusSpeed;
    nucleusGroup.rotation.z += 0.006 * currentNucleusSpeed;

    // Pulse and jitter for each nucleus particle
    nucleusParticles.forEach((particle, index) => {
      const pulse = Math.sin(Date.now() * 0.002 + index) * currentPulseAmplitude + 1;
      particle.mesh.scale.set(pulse, pulse, pulse);
      particle.mesh.material.emissiveIntensity = currentEmissiveIntensity;

      let t = Date.now() * 0.003;
      const p = particle.phase;
      const jx = Math.sin(t * 1.3 + p) * Math.cos(t * 0.7 + p * 2.1) * currentNucleusJitter;
      const jy = Math.sin(t * 1.7 + p * 1.4) * Math.cos(t * 0.9 + p) * currentNucleusJitter;
      const jz = Math.sin(t * 1.1 + p * 0.8) * Math.cos(t * 1.5 + p * 1.7) * currentNucleusJitter;
      particle.mesh.position.set(
        particle.basePos.x + jx,
        particle.basePos.y + jy,
        particle.basePos.z + jz
      );
    
      // Nucleus particle vertex deformation (organic protrusions/arms when thinking)
      t = Date.now() * 0.001 + particle.phase;
      applyNoise(particle.mesh, t, currentNucleusDeformAmp, originalNucleusPositions[index]);
    });
  }

  function applyNoise(mesh, t, displacementAmp, originalPositions) {
    const positions = mesh.geometry.attributes.position;
    const arr = positions.array;

    for (let i = 0; i < arr.length; i += 3) {
      const ox = originalPositions[i];
      const oy = originalPositions[i + 1];
      const oz = originalPositions[i + 2];
      const len = Math.sqrt(ox * ox + oy * oy + oz * oz);
      const nx = ox / len;
      const ny = oy / len;
      const nz = oz / len;
      const d = logo.displacementNoise(ox, oy, oz, t) * displacementAmp;
      arr[i] = ox + nx * d;
      arr[i + 1] = oy + ny * d;
      arr[i + 2] = oz + nz * d;
    }
    positions.needsUpdate = true;
    mesh.geometry.computeVertexNormals();
  }

  onMount(() => {
    init();
  });
</script>

<div class="atom-container" bind:this={container} style="width: {size}px; height: {size}px;"></div>
