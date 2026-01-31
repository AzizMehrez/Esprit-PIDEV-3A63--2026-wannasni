import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Heartbeat Pulse Animation for Emergency/Safety Indicators
export function applyHeartbeatPulse(selector) {
	const el = document.querySelector(selector);
	if (!el) return;
	el.style.transition = 'transform 1.5s cubic-bezier(0.4, 0.0, 0.2, 1)';
	let pulse = () => {
		el.animate([
			{ transform: 'scale(1)' },
			{ transform: 'scale(1.05)' },
			{ transform: 'scale(1)' }
		], {
			duration: 1500,
			iterations: Infinity,
			easing: 'ease-in-out'
		});
	};
	pulse();
}

// Gentle Breathing Animation for Hero Image
export function applyGentleBreathing(selector) {
	const el = document.querySelector(selector);
	if (!el) return;
	el.style.transition = 'transform 4s cubic-bezier(0.4, 0.0, 0.2, 1)';
	let breathe = () => {
		el.animate([
			{ transform: 'scale(1)' },
			{ transform: 'scale(1.02)' },
			{ transform: 'scale(1)' }
		], {
			duration: 4000,
			iterations: Infinity,
			easing: 'ease-in-out'
		});
	};
	breathe();
}

// Button Micro-Interactions
export function enhanceButtonInteractions(selector) {
	document.querySelectorAll(selector).forEach(btn => {
		// Hover: Soft Glow
		btn.addEventListener('mouseenter', () => {
			btn.style.boxShadow = '0 0 0 8px var(--color-accent-soft)';
			btn.style.transition = 'box-shadow 500ms cubic-bezier(0.2, 0.8, 0.2, 1)';
		});
		btn.addEventListener('mouseleave', () => {
			btn.style.boxShadow = '';
		});
		// Click: Micro Compression + Haptic Illusion
		btn.addEventListener('mousedown', () => {
			btn.style.transform = 'scale(0.97)';
			btn.style.boxShadow = '0 0 0 4px var(--color-accent)';
			btn.style.backgroundColor = 'var(--color-accent-soft)';
		});
		btn.addEventListener('mouseup', () => {
			btn.style.transform = 'scale(1)';
			btn.style.backgroundColor = '';
			setTimeout(() => { btn.style.boxShadow = ''; }, 150);
		});
	});
}
