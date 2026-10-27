import axios from "axios";

const apiOrigin = import.meta.env.VITE_API_ORIGIN || '';

axios.interceptors.request.use(config => {
    if (config.url === '/sanctum/csrf-cookie') {
      config.url = `${apiOrigin}/sanctum/csrf-cookie`;
      return config;
    }

    config.baseURL = `${apiOrigin}/api/v1`;
    return config;
  });

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;



export default axios;
