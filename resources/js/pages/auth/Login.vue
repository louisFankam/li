<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { defineAsyncComponent } from 'vue';
const ThreeHealthBackground = defineAsyncComponent(() => import('@/components/ThreeHealthBackground.vue'));
import { Head, useForm } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const cardRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);

onMounted(() => {
    if (titleRef.value) {
        gsap.fromTo(titleRef.value, { opacity: 0, y: -24 }, { opacity: 1, y: 0, duration: 0.9, ease: 'power3.out' });
    }
    if (cardRef.value) {
        gsap.fromTo(cardRef.value, { opacity: 0, y: 40, scale: 0.96 }, { opacity: 1, y: 0, scale: 1, duration: 1.0, ease: 'power3.out', delay: 0.2 });
    }
});
</script>

<template>
    <Head title="Connexion" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-sky-50 via-white to-emerald-50">
        <ThreeHealthBackground />

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10">
            <div ref="cardRef" class="w-full max-w-md rounded-3xl border border-white/60 bg-white/70 p-8 shadow-2xl backdrop-blur-xl">
                <div class="mb-6 text-center">
                    <div ref="titleRef" class="mb-2 inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1 text-sm font-medium text-emerald-700">
                        PharmaGest Pro
                    </div>
                    <h1 class="font-headings text-2xl font-bold text-slate-800">Connexion sécurisée</h1>
                    <p class="mt-1 text-sm text-slate-500">Accédez à votre officine et à votre réseau</p>
                </div>

                <div v-if="status" class="mb-4 text-center text-sm font-medium text-emerald-600">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-5">
                    <div class="grid gap-2">
                        <Label for="email">Adresse e-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="vous@pharmagest.test"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Mot de passe</Label>
                            <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm"> Mot de passe oublié ? </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="••••••••"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" v-model:checked="form.remember" />
                            <span>Se souvenir de moi</span>
                        </Label>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Se connecter
                    </Button>
                </form>

                <div class="mt-6 text-center text-sm text-slate-500">
                    Pas encore de compte ?
                    <TextLink :href="route('register')"> Créer un compte </TextLink>
                </div>
            </div>
        </div>
    </div>
</template>
