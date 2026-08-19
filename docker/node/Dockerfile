FROM node:22-alpine

WORKDIR /var/www/frontend
ENV NEXT_TELEMETRY_DISABLED=1

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate
RUN chown node:node /var/www/frontend
USER node

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml ./

RUN pnpm install --frozen-lockfile --ignore-scripts && pnpm rebuild sharp unrs-resolver

COPY --chown=node:node . .

ARG NEXT_BUILD=true
RUN if [ "$NEXT_BUILD" = "true" ]; then pnpm build; fi
RUN mkdir -p .next

EXPOSE 3000

CMD ["pnpm", "start"]
