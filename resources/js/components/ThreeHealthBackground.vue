<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import * as THREE from 'three';

const canvasRef = ref<HTMLCanvasElement | null>(null);
let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let animationId = 0;
const meshes: THREE.Mesh[] = [];

onMounted(() => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0xf0f7ff, 8, 22);

    camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 0.1, 100);
    camera.position.set(0, 0, 12);

    renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setClearColor(0x000000, 0);

    const ambient = new THREE.AmbientLight(0xffffff, 0.9);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xbfe3ff, 1.1);
    dir.position.set(5, 8, 6);
    scene.add(dir);

    const pastel = [0x93c5fd, 0xa7f3d0, 0xfbcfe8, 0xfde68a, 0xc4b5fd, 0xbfdbfe];
    const mat = (c: number) => new THREE.MeshStandardMaterial({ color: c, roughness: 0.35, metalness: 0.05, transparent: true, opacity: 0.85 });

    function addShape(geometry: THREE.BufferGeometry, color: number, pos: [number, number, number], scale = 1) {
        const m = new THREE.Mesh(geometry, mat(color));
        m.position.set(...pos);
        m.scale.setScalar(scale);
        m.userData = { baseY: pos[1], speed: 0.3 + Math.random() * 0.6, phase: Math.random() * Math.PI * 2 };
        scene!.add(m);
        meshes.push(m);
    }

    // floating capsules (pills)
    for (let i = 0; i < 14; i++) {
        const g = new THREE.CapsuleGeometry(0.25, 0.9, 6, 12);
        addShape(g, pastel[i % pastel.length], [(Math.random() - 0.5) * 16, (Math.random() - 0.5) * 10, (Math.random() - 0.5) * 8], 0.9 + Math.random() * 0.6);
    }
    // soft spheres
    for (let i = 0; i < 10; i++) {
        const g = new THREE.SphereGeometry(0.35, 24, 24);
        addShape(g, pastel[i % pastel.length], [(Math.random() - 0.5) * 18, (Math.random() - 0.5) * 12, (Math.random() - 0.5) * 10], 0.6 + Math.random() * 0.8);
    }
    // medical crosses (two boxes)
    for (let i = 0; i < 6; i++) {
        const g = new THREE.BoxGeometry(0.18, 1.0, 0.18);
        const m1 = new THREE.Mesh(g, mat(0xffffff));
        const m2 = new THREE.Mesh(g, mat(0xffffff));
        m2.rotation.z = Math.PI / 2;
        const group = new THREE.Group();
        group.add(m1, m2);
        group.position.set((Math.random() - 0.5) * 16, (Math.random() - 0.5) * 10, (Math.random() - 0.5) * 8);
        group.scale.setScalar(0.7 + Math.random() * 0.5);
        group.userData = { baseY: group.position.y, speed: 0.2 + Math.random() * 0.5, phase: Math.random() * Math.PI * 2 };
        scene.add(group);
        meshes.push(group as unknown as THREE.Mesh);
    }

    const clock = new THREE.Clock();
    const animate = () => {
        animationId = requestAnimationFrame(animate);
        const t = clock.getElapsedTime();
        meshes.forEach((m) => {
            m.rotation.x += 0.003;
            m.rotation.y += 0.005;
            m.position.y = m.userData.baseY + Math.sin(t * m.userData.speed + m.userData.phase) * 0.6;
        });
        if (camera) camera.position.x = Math.sin(t * 0.15) * 0.8;
        if (renderer && scene && camera) renderer.render(scene, camera);
    };
    animate();

    const onResize = () => {
        if (!camera || !renderer) return;
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    };
    window.addEventListener('resize', onResize);

    onBeforeUnmount(() => {
        window.removeEventListener('resize', onResize);
        cancelAnimationFrame(animationId);
        meshes.forEach((m) => {
            (m as any).geometry?.dispose?.();
            (m as any).material?.dispose?.();
        });
        renderer?.dispose();
    });
});
</script>

<template>
    <canvas ref="canvasRef" class="fixed inset-0 -z-10 h-full w-full"></canvas>
</template>
