import { Cross, Droplet, Flame, Gem, HandHeart, Wheat } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

/** The icon of each sacrament. */
export const sacramentIcon: Record<string, LucideIcon> = {
    baptism: Droplet,
    first_holy_communion: Wheat,
    confirmation: Flame,
    matrimony: Gem,
    holy_orders: Cross,
    anointing_of_the_sick: HandHeart,
};

/** The badge tone of each sacrament. */
export const sacramentTone: Record<string, string> = {
    baptism: 'blue',
    first_holy_communion: 'amber',
    confirmation: 'red',
    matrimony: 'rose',
    holy_orders: 'purple',
    anointing_of_the_sick: 'teal',
};

/** The icon tile colours of each sacrament. */
export const sacramentTile: Record<string, string> = {
    baptism: 'bg-blue-500/10 text-blue-600 dark:text-blue-300',
    first_holy_communion: 'bg-amber-500/10 text-amber-600 dark:text-amber-300',
    confirmation: 'bg-red-500/10 text-red-600 dark:text-red-300',
    matrimony: 'bg-rose-500/10 text-rose-600 dark:text-rose-300',
    holy_orders: 'bg-violet-500/10 text-violet-600 dark:text-violet-300',
    anointing_of_the_sick: 'bg-teal-500/10 text-teal-600 dark:text-teal-300',
};

/** The sacraments of initiation, which every member's profile tracks. */
export const initiationSacraments = [
    'baptism',
    'first_holy_communion',
    'confirmation',
];
