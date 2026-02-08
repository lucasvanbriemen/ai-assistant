<script>
  import { onMount } from 'svelte';
  import * as THREE from 'three';

  let { size = 280, animate = true } = $props();

  let container;
  let scene, camera, renderer;
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

    // Create multiple nucleus particles that orbit each other
    const nucleusConfigs = [
      { color: 0x8b5cf6, emissive: 0x6366f1, angle: 0, radius: 0.4 },
      { color: 0x6366f1, emissive: 0x8b5cf6, angle: (Math.PI * 2) / 3, radius: 0.4 },
      { color: 0x3b82f6, emissive: 0x6366f1, angle: (Math.PI * 4) / 3, radius: 0.4 }
    ];

    nucleusConfigs.forEach(config => {
      // Create nucleus particle with highly reflective material
      const nucleusGeometry = new THREE.SphereGeometry(0.4, 32, 32);
      const nucleusMaterial = new THREE.MeshPhongMaterial({
        color: config.color,
        emissive: config.emissive,
        emissiveIntensity: 1.2, // Reduced to allow more reflection visibility
        shininess: 200, // Very high shininess for reflections
        transparent: true,
        opacity: 0.95,
        specular: 0xffffff, // White specular for bright reflections
        reflectivity: 1.0 // Maximum reflectivity
      });
      const nucleusParticle = new THREE.Mesh(nucleusGeometry, nucleusMaterial);

      // Add outer glow to each particle
      const glowGeometry = new THREE.SphereGeometry(0.55, 32, 32);
      const glowMaterial = new THREE.MeshBasicMaterial({
        color: config.color,
        transparent: true,
        opacity: 0.6
      });
      const glow = new THREE.Mesh(glowGeometry, glowMaterial);
      nucleusParticle.add(glow);

      // Store particle with initial angle, radius, and rotation speeds
      nucleusParticles.push({
        mesh: nucleusParticle,
        angle: config.angle,
        radius: config.radius,
        rotationSpeed: { x: 0.02, y: 0.015, z: 0.01 } // Individual rotation speeds
      });

      scene.add(nucleusParticle);
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

    // Animate nucleus particles - both orbiting AND rotating (tumbling)
    nucleusParticles.forEach((particle, index) => {
      particle.angle += 0.015; // Orbit speed

      // Calculate position in 3D orbit (not just flat circle)
      const x = Math.cos(particle.angle) * particle.radius;
      const y = Math.sin(particle.angle) * particle.radius;
      const z = Math.sin(particle.angle * 2) * 0.2; // Add some Z variation

      particle.mesh.position.set(x, y, z);

      // Make each particle rotate/tumble on its own axes
      particle.mesh.rotation.x += particle.rotationSpeed.x;
      particle.mesh.rotation.y += particle.rotationSpeed.y;
      particle.mesh.rotation.z += particle.rotationSpeed.z;

      // Subtle pulse for each particle
      const pulse = Math.sin(Date.now() * 0.002 + particle.angle) * 0.05 + 1;
      particle.mesh.scale.set(pulse, pulse, pulse);
    });

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
