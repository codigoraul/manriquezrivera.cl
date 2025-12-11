// Configuración de WordPress API
const WP_URL = import.meta.env.PUBLIC_WP_URL || 'http://localhost:8888/manriquezrivera';
const WP_MEDIA_URL = import.meta.env.PUBLIC_WP_MEDIA_URL?.replace(/\/$/, '');

function resolveMediaUrl(url: string | undefined): string | undefined {
  if (!url) return url;
  if (!WP_MEDIA_URL) return url;
  const normalizedMediaBase = WP_MEDIA_URL.replace(/\/$/, '');
  const wpContentIndex = url.indexOf('/wp-content/');

  if (wpContentIndex !== -1) {
    return `${normalizedMediaBase}${url.substring(wpContentIndex)}`;
  }

  // Si WordPress cambia el path, al menos reemplazamos el hostname original
  return url.replace(/^https?:\/\/[^/]+/, normalizedMediaBase);
}

export interface Service {
  id: number;
  title: {
    rendered: string;
  };
  content: {
    rendered: string;
  };
  excerpt: {
    rendered: string;
  };
  slug: string;
  acf?: {
    icono?: string;
    areas_practica?: Array<{
      titulo: string;
      descripcion: string;
      icono: string;
    }>;
    imagen_destacada?: string;
    orden?: number;
  };
  _embedded?: {
    'wp:featuredmedia'?: Array<{
      source_url: string;
      alt_text: string;
    }>;
  };
}

export interface Post {
  id: number;
  title: {
    rendered: string;
  };
  content: {
    rendered: string;
  };
  excerpt: {
    rendered: string;
  };
  slug: string;
  date?: string;
  categories?: number[];
  _embedded?: {
    'wp:featuredmedia'?: Array<{
      source_url: string;
      alt_text: string;
    }>;
    'wp:term'?: Array<Array<{
      id: number;
      name: string;
      slug: string;
    }>>;
  };
}

// Obtener todos los servicios
export async function getServices(): Promise<Service[]> {
  try {
    const response = await fetch(
      `${WP_URL}/wp-json/wp/v2/servicio?_embed&per_page=100`
    );
    
    if (!response.ok) {
      console.error('Error fetching services:', response.statusText);
      return [];
    }
    
    const services = await response.json();
    return services;
  } catch (error) {
    console.error('Error connecting to WordPress:', error);
    return [];
  }
}

// Obtener un servicio por slug
export async function getServiceBySlug(slug: string): Promise<Service | null> {
  try {
    const response = await fetch(
      `${WP_URL}/wp-json/wp/v2/servicio?slug=${slug}&_embed`
    );
    
    if (!response.ok) {
      return null;
    }
    
    const services = await response.json();
    return services[0] || null;
  } catch (error) {
    console.error('Error fetching service:', error);
    return null;
  }
}

// Obtener servicios por tipo (empresas o personas)
export async function getServicesByType(tipo: string, limit?: number): Promise<Service[]> {
  try {
    // IDs fijos de taxonomía en WP:
    // tipo_servicio=3 → Empresas
    // tipo_servicio=4 → Personas
    const tipoIds: Record<string, number> = {
      empresas: 3,
      personas: 4
    };

    const taxId = tipoIds[tipo];

    if (!taxId) {
      console.error(`[getServicesByType] Unknown tipo "${tipo}"`);
      return [];
    }

    // Obtener servicios filtrados por taxonomía directamente
    const perPage = limit || 100;
    const servicesUrl = `${WP_URL}/wp-json/wp/v2/servicio?tipo_servicio=${taxId}&_embed&per_page=${perPage}`;
    console.error('[getServicesByType] Fetch services URL:', servicesUrl);

    const response = await fetch(servicesUrl);
    
    if (!response.ok) {
      console.error('Error fetching services by type:', response.status, response.statusText);
      return [];
    }
    
    const services = await response.json();
    return services;
  } catch (error) {
    console.error('Error connecting to WordPress:', error);
    return [];
  }
}

// Obtener imagen destacada
export function getFeaturedImage(service: Service): string {
  if (service._embedded?.['wp:featuredmedia']?.[0]?.source_url) {
    return (
      resolveMediaUrl(service._embedded['wp:featuredmedia'][0].source_url) ||
      service._embedded['wp:featuredmedia'][0].source_url
    );
  }
  // Usar BASE_URL si está disponible, sino usar ruta relativa
  const baseUrl = import.meta.env.BASE_URL || '';
  return `${baseUrl}/images/servicio-default.jpg`;
}

// Obtener posts del blog
export async function getPosts(perPage = 9): Promise<Post[]> {
  try {
    const response = await fetch(
      `${WP_URL}/wp-json/wp/v2/posts?_embed&per_page=${perPage}`
    );

    if (!response.ok) {
      console.error('Error fetching posts:', response.statusText);
      return [];
    }

    return await response.json();
  } catch (error) {
    console.error('Error connecting to WordPress (posts):', error);
    return [];
  }
}

export async function getAllPosts(): Promise<Post[]> {
  return getPosts(100);
}

export async function getPostBySlug(slug: string): Promise<Post | null> {
  try {
    const response = await fetch(
      `${WP_URL}/wp-json/wp/v2/posts?slug=${slug}&_embed`
    );

    if (!response.ok) {
      return null;
    }

    const posts = await response.json();
    return posts[0] || null;
  } catch (error) {
    console.error('Error fetching post:', error);
    return null;
  }
}

export function getPostFeaturedImage(post: Post): string {
  if (post._embedded?.['wp:featuredmedia']?.[0]?.source_url) {
    return post._embedded['wp:featuredmedia'][0].source_url;
  }
  const baseUrl = import.meta.env.BASE_URL || '';
  return `${baseUrl}/images/blog-default.jpg`;
}

export function getPrimaryCategory(post: Post): string | null {
  const categories = post._embedded?.['wp:term']?.[0];
  if (categories && categories.length > 0) {
    return categories[0].name;
  }
  return null;
}
