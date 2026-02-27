export default {
  // 3-octave sine-based pseudo-noise for vertex displacement
  displacementNoise(x, y, z, t) {
    let value = 0;
    // Octave 1 — low frequency, large shape
    value += Math.sin(x * 1.7 + t * 0.8) * Math.sin(y * 2.3 + t * 0.6) * Math.sin(z * 1.9 + t * 0.7);
    // Octave 2 — medium frequency, medium detail
    value += 0.5 * Math.sin(x * 3.1 + t * 1.3) * Math.sin(y * 4.7 + t * 1.1) * Math.sin(z * 3.7 + t * 0.9);
    // Octave 3 — high frequency, fine bumps
    value += 0.25 * Math.sin(x * 7.3 + t * 2.1) * Math.sin(y * 5.9 + t * 1.7) * Math.sin(z * 8.1 + t * 1.4);
    return value;
  }
};