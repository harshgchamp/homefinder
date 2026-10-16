// Why: native <select> and number inputs need to look like the shadcn <Input>,
// but that component only accepts string | number (no null). One shared class
// string keeps every form field identical.
export const fieldClass =
    'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive md:text-sm dark:bg-input/30';
