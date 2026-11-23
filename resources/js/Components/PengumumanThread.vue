<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { btnPrimary, btnSecondary, inputClass } from '@/ui';
import { askConfirm } from '@/confirm';

const emit = defineEmits(['compose']);

defineProps({
    posts: { type: Array, default: () => [] },
});

const commentDrafts = reactive({});
const replyDrafts = reactive({});
const openReplies = ref([]);

function toggleReply(id) {
    openReplies.value = openReplies.value.includes(id)
        ? openReplies.value.filter((item) => item !== id)
        : [...openReplies.value, id];
}

function sendComment(post) {
    const text = (commentDrafts[post.id_post] ?? '').trim();

    if (!text) {
        return;
    }

    router.post(`/pengumuman/${post.id_post}/komentar`, { text }, {
        preserveScroll: true,
        onSuccess: () => {
            commentDrafts[post.id_post] = '';
        },
    });
}

function sendReply(comment) {
    const text = (replyDrafts[comment.id_comment] ?? '').trim();

    if (!text) {
        return;
    }

    router.post(`/pengumuman/komentar/${comment.id_comment}/balasan`, { text }, {
        preserveScroll: true,
        onSuccess: () => {
            replyDrafts[comment.id_comment] = '';
            openReplies.value = openReplies.value.filter((item) => item !== comment.id_comment);
        },
    });
}

async function removePost(post) {
    if (await askConfirm(`Hapus pengumuman dari ${post.author} (${post.tanggal})? Seluruh komentar dan balasannya ikut terhapus.`)) {
        router.delete(`/pengumuman/${post.id_post}`, { preserveScroll: true });
    }
}

async function removeComment(comment) {
    if (await askConfirm(`Hapus komentar ${comment.author}? Balasan di dalamnya ikut terhapus.`)) {
        router.delete(`/pengumuman/komentar/${comment.id_comment}`, { preserveScroll: true });
    }
}

async function removeReply(reply) {
    if (await askConfirm(`Hapus balasan ${reply.author}?`)) {
        router.delete(`/pengumuman/balasan/${reply.id_reply}`, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="!posts.length"
            class="rounded-xl border border-border bg-card px-6 py-12 text-center shadow-card"
        >
            <i class="fas fa-bullhorn mx-auto block text-4xl text-muted-foreground/30" aria-hidden="true"></i>
            <p class="mt-2 text-sm text-muted-foreground">Belum ada pengumuman.</p>
            <button type="button" :class="btnPrimary" class="mx-auto mt-4" @click="emit('compose')">
                <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tulis Pengumuman
            </button>
        </div>

        <article v-for="post in posts" :key="post.id_post" class="rounded-xl border border-border bg-card shadow-card">
            <div class="flex flex-wrap items-start gap-3 border-b border-border px-5 py-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground">{{ post.author }}</p>
                    <p class="text-xs text-muted-foreground">{{ post.tanggal }}</p>
                </div>
                <p v-if="post.targets.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="target in post.targets"
                        :key="target"
                        class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                    >
                        {{ target }}
                    </span>
                </p>
                <button
                    v-if="post.mine"
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                    :aria-label="`Hapus pengumuman dari ${post.author}`"
                    @click="removePost(post)"
                >
                    <i class="fas fa-trash-alt h-4 w-4" aria-hidden="true" ></i>
                </button>
            </div>

            <p class="whitespace-pre-line px-5 py-4 text-sm leading-relaxed text-foreground">{{ post.text }}</p>

            <div class="space-y-4 border-t border-border bg-muted/50 px-5 py-4">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    <i class="fas fa-comment h-4 w-4" aria-hidden="true" ></i>
                    {{ post.comments.length }} komentar
                </p>

                <div v-for="comment in post.comments" :key="comment.id_comment" class="space-y-3 rounded-lg border border-border bg-card px-4 py-3">
                    <div class="flex flex-wrap items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground">{{ comment.author }}</p>
                            <p class="text-xs text-muted-foreground">{{ comment.tanggal }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs font-medium text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                            :aria-expanded="openReplies.includes(comment.id_comment)"
                            @click="toggleReply(comment.id_comment)"
                        >
                            Balas
                        </button>
                        <button
                            v-if="comment.mine"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                            :aria-label="`Hapus komentar dari ${comment.author}`"
                            @click="removeComment(comment)"
                        >
                            <i class="fas fa-trash-alt h-3.5 w-3.5" aria-hidden="true" ></i>
                        </button>
                    </div>
                    <p class="whitespace-pre-line text-sm text-foreground">{{ comment.text }}</p>

                    <div v-if="comment.replies.length" class="space-y-2 border-l-2 border-border pl-4">
                        <div v-for="reply in comment.replies" :key="reply.id_reply" class="flex items-start gap-2">
                            <i class="fas fa-arrow-turn-down mt-1 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden="true" ></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-foreground">
                                    {{ reply.author }}
                                    <span class="font-normal text-muted-foreground">· {{ reply.tanggal }}</span>
                                </p>
                                <p class="whitespace-pre-line text-sm text-muted-foreground">{{ reply.text }}</p>
                            </div>
                            <button
                                v-if="reply.mine"
                                type="button"
                                class="rounded-lg p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                                aria-label="Hapus balasan"
                                @click="removeReply(reply)"
                            >
                                <i class="fas fa-trash-alt h-3.5 w-3.5" aria-hidden="true" ></i>
                            </button>
                        </div>
                    </div>

                    <form v-if="openReplies.includes(comment.id_comment)" class="flex flex-wrap items-end gap-2" @submit.prevent="sendReply(comment)">
                        <div class="min-w-0 flex-1">
                            <label class="sr-only" :for="`balasan-${comment.id_comment}`">Balasan untuk {{ comment.author }}</label>
                            <input
 :id="`balasan-${comment.id_comment}`"
 v-model="replyDrafts[comment.id_comment]"
 :class="inputClass"
 type="text"
 maxlength="2000"
 placeholder="Tulis balasan"
 />
                        </div>
                        <button type="submit" :class="btnPrimary">
                            <i class="fas fa-paper-plane h-4 w-4" aria-hidden="true" ></i> Kirim
                        </button>
                    </form>
                </div>

                <form class="flex flex-wrap items-end gap-2" @submit.prevent="sendComment(post)">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" :for="`komentar-${post.id_post}`">Komentar untuk pengumuman {{ post.tanggal }}</label>
                        <input
 :id="`komentar-${post.id_post}`"
 v-model="commentDrafts[post.id_post]"
 :class="inputClass"
 type="text"
 maxlength="2000"
 placeholder="Tulis komentar"
 />
                    </div>
                    <button type="submit" :class="btnSecondary">
                        <i class="fas fa-paper-plane h-4 w-4" aria-hidden="true" ></i> Komentar
                    </button>
                </form>
            </div>
        </article>
    </div>
</template>
