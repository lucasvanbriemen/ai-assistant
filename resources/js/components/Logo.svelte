<script>
  import * as THREE from 'three';
  import logo from '@/lib/logo.js';

  import { onMount } from 'svelte';

  let { size = 280, state = "thinking" } = $props();

  const ALLOWED_STATES = ["normal", "thinking"];

  const ELECTRON_SPEEDS = { normal: 2.0, thinking: 6.0 };
  const SCENE_SPEEDS = { normal: 25.0, thinking: 50.0 };
  const NUCLEUS_SPEEDS = { normal: 5.0, thinking: 25.0 };

  // Normal state speeds
  const NORMAL_PULSE_AMPLITUDE = 0.06;
  const NORMAL_EMISSIVE_INTENSITY = 0.4;
  const NORMAL_ELECTRON_LIGHT_INTENSITY = 125.0;
  const NORMAL_RIM_OPACITY = 0.1;

  // Thinking state speeds
  const THINKING_PULSE_AMPLITUDE = 0.15;
  const THINKING_EMISSIVE_INTENSITY = 0.8;
  const THINKING_ELECTRON_LIGHT_INTENSITY = 250.0;
  const THINKING_RIM_OPACITY = 0.25;

  // Interpolated values (smoothly transition between states)
  let currentElectronSpeed = ELECTRON_SPEEDS[state];
  let currentSceneSpeed = SCENE_SPEEDS[state];
  let currentNucleusSpeed = NUCLEUS_SPEEDS[state];
  let currentPulseAmplitude = NORMAL_PULSE_AMPLITUDE;
  let currentEmissiveIntensity = NORMAL_EMISSIVE_INTENSITY;
  let currentElectronLightIntensity = NORMAL_ELECTRON_LIGHT_INTENSITY;
  let currentRimOpacity = NORMAL_RIM_OPACITY;
  const ORBIT_RADIUS = 3.2;
  const ORBIT_TUBE_THICKNESS = 0.05;
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
  let glassSphere; // Reference to glass sphere for displacement
  let originalPositions = null; // Store original vertex positions
  let originalRimPositions = null; // Store original rim vertex positions
  let originalNucleusPositions = []; // Store original nucleus vertex positions (one per particle)
  let currentDisplacementAmp = 0; // Current displacement amplitude (lerped)
  let currentNucleusJitter = 0; // Current nucleus jitter amplitude (lerped)
  let currentRimDisplacementAmp = 0; // Current rim displacement amplitude (lerped)
  let currentNucleusDeformAmp = 0; // Current nucleus deform amplitude (lerped)

  const NORMAL_DISPLACEMENT_AMP = 0;
  const THINKING_DISPLACEMENT_AMP = 0.15;
  const NORMAL_NUCLEUS_JITTER = 0;
  const THINKING_NUCLEUS_JITTER = 0.04;
  const NORMAL_RIM_DISPLACEMENT_AMP = 0;
  const THINKING_RIM_DISPLACEMENT_AMP = 0.2;
  const NORMAL_NUCLEUS_DEFORM_AMP = 0;
  const THINKING_NUCLEUS_DEFORM_AMP = 0.06;

  function init() {
    if (!ALLOWED_STATES.includes(state)) {
      return console.error(`Invalid state "${state}" for Logo component. Allowed states: ${ALLOWED_STATES.join(", ")}`);
    }

    initThreeJS();
    animateScene();
  }
  
  function initThreeJS() {
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
      const nucleusGeometry = new THREE.SphereGeometry(0.35, 32, 32); // Reduced segments (still smooth, fewer vertices to displace)
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

      // Store original vertex positions for deformation
      const origNucPos = new Float32Array(nucleusGeometry.attributes.position.array);
      originalNucleusPositions.push(origNucPos);

      nucleusParticles.push({
        mesh: nucleusParticle,
        basePos: { x: pos.x, y: pos.y, z: pos.z },
        phase: index * 1.7 // Different phase per particle for organic motion
      });
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
    const glassGeometry = new THREE.SphereGeometry(3.5, 64, 64);
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
    glassSphere = new THREE.Mesh(glassGeometry, glassMaterial);
    glassSphere.renderOrder = 999;
    scene.add(glassSphere);

    // Store original vertex positions for displacement
    originalPositions = new Float32Array(glassGeometry.attributes.position.array);

    // Subtle rim glow on glass edge
    const rimGeometry = new THREE.SphereGeometry(3.75, 64, 64);
    const rimMaterial = new THREE.MeshBasicMaterial({
      color: 0x8b5cf6,
      transparent: true,
      opacity: currentRimOpacity,
      side: THREE.BackSide
    });
    rimMesh = new THREE.Mesh(rimGeometry, rimMaterial);
    scene.add(rimMesh);

    // Store original rim vertex positions for displacement
    originalRimPositions = new Float32Array(rimGeometry.attributes.position.array);

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
    const targetElectronSpeed = ELECTRON_SPEEDS[state];
    const targetSceneSpeed = SCENE_SPEEDS[state];
    const targetNucleusSpeed = NUCLEUS_SPEEDS[state];
    const targetPulseAmplitude = state == "thinking" ? THINKING_PULSE_AMPLITUDE : NORMAL_PULSE_AMPLITUDE;
    const targetEmissiveIntensity = state == "thinking" ? THINKING_EMISSIVE_INTENSITY : NORMAL_EMISSIVE_INTENSITY;
    const targetElectronLightIntensity = state == "thinking" ? THINKING_ELECTRON_LIGHT_INTENSITY : NORMAL_ELECTRON_LIGHT_INTENSITY;
    const targetRimOpacity = state == "thinking" ? THINKING_RIM_OPACITY : NORMAL_RIM_OPACITY;

    const targetDisplacementAmp = state == "thinking" ? THINKING_DISPLACEMENT_AMP : NORMAL_DISPLACEMENT_AMP;
    const targetNucleusJitter = state == "thinking" ? THINKING_NUCLEUS_JITTER : NORMAL_NUCLEUS_JITTER;
    const targetRimDisplacementAmp = state == "thinking" ? THINKING_RIM_DISPLACEMENT_AMP : NORMAL_RIM_DISPLACEMENT_AMP;
    const targetNucleusDeformAmp = state == "thinking" ? THINKING_NUCLEUS_DEFORM_AMP : NORMAL_NUCLEUS_DEFORM_AMP;

    currentElectronSpeed = lerp(currentElectronSpeed, targetElectronSpeed, lerpSpeed);
    currentSceneSpeed = lerp(currentSceneSpeed, targetSceneSpeed, lerpSpeed);
    currentNucleusSpeed = lerp(currentNucleusSpeed, targetNucleusSpeed, lerpSpeed);
    currentPulseAmplitude = lerp(currentPulseAmplitude, targetPulseAmplitude, lerpSpeed);
    currentEmissiveIntensity = lerp(currentEmissiveIntensity, targetEmissiveIntensity, lerpSpeed);
    currentElectronLightIntensity = lerp(currentElectronLightIntensity, targetElectronLightIntensity, lerpSpeed);
    currentRimOpacity = lerp(currentRimOpacity, targetRimOpacity, lerpSpeed);
    currentDisplacementAmp = lerp(currentDisplacementAmp, targetDisplacementAmp, lerpSpeed);
    currentNucleusJitter = lerp(currentNucleusJitter, targetNucleusJitter, lerpSpeed);
    currentRimDisplacementAmp = lerp(currentRimDisplacementAmp, targetRimDisplacementAmp, lerpSpeed);
    currentNucleusDeformAmp = lerp(currentNucleusDeformAmp, targetNucleusDeformAmp, lerpSpeed);

    // Rotate entire scene for 3D effect
    scene.rotation.y += 0.002 * currentSceneSpeed;
    scene.rotation.x += 0.001 * currentSceneSpeed;

    // Glass sphere vertex displacement (wobbly unstable shape when thinking)
    if (glassSphere && originalPositions) {
      const positions = glassSphere.geometry.attributes.position;
      const arr = positions.array;

      if (currentDisplacementAmp > 0.001) {
        const t = Date.now() * 0.001;
        for (let i = 0; i < arr.length; i += 3) {
          const ox = originalPositions[i];
          const oy = originalPositions[i + 1];
          const oz = originalPositions[i + 2];
          // Radial direction (normalized)
          const len = Math.sqrt(ox * ox + oy * oy + oz * oz);
          const nx = ox / len;
          const ny = oy / len;
          const nz = oz / len;
          // Displace along radial direction
          const d = logo.displacementNoise(ox, oy, oz, t) * currentDisplacementAmp;
          arr[i] = ox + nx * d;
          arr[i + 1] = oy + ny * d;
          arr[i + 2] = oz + nz * d;
        }
        positions.needsUpdate = true;
        glassSphere.geometry.computeVertexNormals();
      } else if (currentDisplacementAmp <= 0.001 && currentDisplacementAmp > -0.001) {
        // Restore original positions when idle (snap back)
        let needsRestore = false;
        for (let i = 0; i < arr.length; i++) {
          if (arr[i] !== originalPositions[i]) {
            needsRestore = true;
            break;
          }
        }
        if (needsRestore) {
          arr.set(originalPositions);
          positions.needsUpdate = true;
          glassSphere.geometry.computeVertexNormals();
        }
      }
    }

    // Rim sphere vertex displacement (wobbly container when thinking)
    if (rimMesh && originalRimPositions) {
      const rimPositions = rimMesh.geometry.attributes.position;
      const rimArr = rimPositions.array;

      if (currentRimDisplacementAmp > 0.001) {
        const t = Date.now() * 0.001 * 0.7; // Slower rate than glass sphere to avoid lockstep
        for (let i = 0; i < rimArr.length; i += 3) {
          const ox = originalRimPositions[i];
          const oy = originalRimPositions[i + 1];
          const oz = originalRimPositions[i + 2];
          const len = Math.sqrt(ox * ox + oy * oy + oz * oz);
          const nx = ox / len;
          const ny = oy / len;
          const nz = oz / len;
          const d = logo.displacementNoise(ox, oy, oz, t) * currentRimDisplacementAmp;
          rimArr[i] = ox + nx * d;
          rimArr[i + 1] = oy + ny * d;
          rimArr[i + 2] = oz + nz * d;
        }
        rimPositions.needsUpdate = true;
        rimMesh.geometry.computeVertexNormals();
      } else if (currentRimDisplacementAmp <= 0.001 && currentRimDisplacementAmp > -0.001) {
        let needsRestore = false;
        for (let i = 0; i < rimArr.length; i++) {
          if (rimArr[i] !== originalRimPositions[i]) {
            needsRestore = true;
            break;
          }
        }
        if (needsRestore) {
          rimArr.set(originalRimPositions);
          rimPositions.needsUpdate = true;
          rimMesh.geometry.computeVertexNormals();
        }
      }
    }

    // Rotate the entire nucleus group so particles swap positions in 3D
    nucleusGroup.rotation.x += 0.008 * currentNucleusSpeed;
    nucleusGroup.rotation.y += 0.012 * currentNucleusSpeed;
    nucleusGroup.rotation.z += 0.006 * currentNucleusSpeed;

    // Pulse and jitter for each nucleus particle
    nucleusParticles.forEach((particle, index) => {
      const pulse = Math.sin(Date.now() * 0.002 + index) * currentPulseAmplitude + 1;
      particle.mesh.scale.set(pulse, pulse, pulse);

      // Update emissive intensity
      particle.mesh.material.emissiveIntensity = currentEmissiveIntensity;

      // Smooth sine-based jitter when thinking
      if (currentNucleusJitter > 0.001) {
        const t = Date.now() * 0.003;
        const p = particle.phase;
        const jx = Math.sin(t * 1.3 + p) * Math.cos(t * 0.7 + p * 2.1) * currentNucleusJitter;
        const jy = Math.sin(t * 1.7 + p * 1.4) * Math.cos(t * 0.9 + p) * currentNucleusJitter;
        const jz = Math.sin(t * 1.1 + p * 0.8) * Math.cos(t * 1.5 + p * 1.7) * currentNucleusJitter;
        particle.mesh.position.set(
          particle.basePos.x + jx,
          particle.basePos.y + jy,
          particle.basePos.z + jz
        );
      } else {
        particle.mesh.position.set(particle.basePos.x, particle.basePos.y, particle.basePos.z);
      }

      // Nucleus particle vertex deformation (organic protrusions/arms when thinking)
      if (originalNucleusPositions[index]) {
        const nucPositions = particle.mesh.geometry.attributes.position;
        const nucArr = nucPositions.array;
        const origNuc = originalNucleusPositions[index];

        if (currentNucleusDeformAmp > 0.001) {
          const t = Date.now() * 0.001 + particle.phase; // Phase offset per particle
          for (let i = 0; i < nucArr.length; i += 3) {
            const ox = origNuc[i];
            const oy = origNuc[i + 1];
            const oz = origNuc[i + 2];
            const len = Math.sqrt(ox * ox + oy * oy + oz * oz);
            const nx = ox / len;
            const ny = oy / len;
            const nz = oz / len;
            const d = logo.displacementNoise(ox, oy, oz, t) * currentNucleusDeformAmp;
            nucArr[i] = ox + nx * d;
            nucArr[i + 1] = oy + ny * d;
            nucArr[i + 2] = oz + nz * d;
          }
          nucPositions.needsUpdate = true;
          particle.mesh.geometry.computeVertexNormals();
        } else if (currentNucleusDeformAmp <= 0.001 && currentNucleusDeformAmp > -0.001) {
          let needsRestore = false;
          for (let i = 0; i < nucArr.length; i++) {
            if (nucArr[i] !== origNuc[i]) {
              needsRestore = true;
              break;
            }
          }
          if (needsRestore) {
            nucArr.set(origNuc);
            nucPositions.needsUpdate = true;
            particle.mesh.geometry.computeVertexNormals();
          }
        }
      }
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

    // Slowly rotate orbits + opacity flicker when thinking
    orbits.forEach((orbit, index) => {
      orbit.rotation.z += 0.001 * (index + 1) * currentSceneSpeed;

      // Subtle opacity flicker when thinking
      if (currentDisplacementAmp > 0.001) {
        const flicker = Math.sin(Date.now() * 0.004 + index * 2.3) * 0.1 * (currentDisplacementAmp / THINKING_DISPLACEMENT_AMP);
        orbit.material.opacity = 0.4 + flicker;
      } else {
        orbit.material.opacity = 0.4;
      }
    });

    // Update rim glow opacity
    if (rimMesh) {
      rimMesh.material.opacity = currentRimOpacity;
    }

    renderer.render(scene, camera);
  }

  onMount(() => {
    init();
  });
</script>

<div class="atom-container" bind:this={container} style="width: {size}px; height: {size}px;"></div>
