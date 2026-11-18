import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Item } from "./Item.vue"
export { default as ItemActions } from "./ItemActions.vue"
export { default as ItemContent } from "./ItemContent.vue"
export { default as ItemDescription } from "./ItemDescription.vue"
export { default as ItemMedia } from "./ItemMedia.vue"
export { default as ItemTitle } from "./ItemTitle.vue"

export const itemVariants = cva(
  "group/item flex items-center border border-transparent text-sm rounded-2xl transition-all duration-150 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [a&]:active:scale-[0.985] [a&]:hover:bg-accent/50 [button&]:active:scale-[0.985] [button&]:hover:bg-accent/50",
  {
    variants: {
      variant: {
        default: "bg-transparent",
        outline: "border-border",
        card: "bg-card border-border shadow-xs",
        muted: "bg-muted/50",
      },
      size: {
        default: "p-4 gap-3.5",
        sm: "py-3 px-4 gap-2.5",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export const itemMediaVariants = cva(
  "flex shrink-0 items-center justify-center gap-2 [&_svg]:pointer-events-none",
  {
    variants: {
      variant: {
        default: "bg-transparent",
        icon: "size-11 rounded-xl bg-muted [&_svg:not([class*='size-'])]:size-5",
        image: "size-11 rounded-xl overflow-hidden [&_img]:size-full [&_img]:object-cover",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type ItemVariants = VariantProps<typeof itemVariants>
export type ItemMediaVariants = VariantProps<typeof itemMediaVariants>
