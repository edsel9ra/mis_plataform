FROM node:22-alpine

WORKDIR /var/www/frontend

RUN npm install -g pnpm

COPY --chown=node:node package.json pnpm-workspace.yaml ./

RUN pnpm install --ignore-scripts && pnpm rebuild sharp unrs-resolver

EXPOSE 3000

CMD ["pnpm", "dev"]
