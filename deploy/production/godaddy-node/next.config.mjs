/** Configuration for the isolated GoDaddy pilot package only. */
export default {
  output: 'standalone',
  eslint: { ignoreDuringBuilds: true },
  images: { unoptimized: true },
  // GoDaddy secrets are runtime-only. These public routing values must also
  // exist when Next compiles the browser bundle, including clean build workers.
  // The gateway key remains server-only and is deliberately absent here.
  env: {
    NEXT_PUBLIC_API_BASE_URL: '/api',
    NEXT_PUBLIC_API_URL: 'https://z1izdz2qau.preview.c36.airoapp.ai',
  },
};
