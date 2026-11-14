import { z } from 'zod';

export const messageSchema = z.object({
  content: z.string().trim().min(1, { error: 'messages.send_error' }).max(5000),
});

export type MessageFormValues = z.output<typeof messageSchema>;
export type MessageFormInput = z.input<typeof messageSchema>;
