FROM node:22-alpine

WORKDIR /app

# Install packages first so this layer is cached when only the code changes
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

EXPOSE 5173

# Vite dev server; its /api proxy forwards to API_TARGET (the backend container)
CMD ["npm", "run", "dev"]
