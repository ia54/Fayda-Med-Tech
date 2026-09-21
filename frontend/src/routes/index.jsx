import { createBrowserRouter } from "react-router";
import RootLayout from "../layout/RootLayout";
import Home from "../pages/root/home/Home";
import Error from "../pages/Error";
import About from "../pages/root/about/About";
import Solutions from "../pages/root/solutions/Solutions";
import Pricing from "../pages/root/pricing/Pricing";
import Contact from "../pages/root/contact/Contact";
import Analysis from "../pages/root/product/Analysis";
import Scale from "../pages/root/product/Scale";
import Developer from "../pages/root/product/Developer";
import Privacy from "../pages/root/legal/Privacy";
import Terms from "../pages/root/legal/Terms";

export const router = createBrowserRouter([
  {
    path: "/",
    element: <RootLayout />,
    errorElement: <Error />,
    children: [
      {
        path: "/",
        element: <Home />,
      },
      {
        path: "/solutions",
        element: <Solutions />,
      },
      {
        path: "/about-us",
        element: <About />,
      },
      {
        path: "/pricing",
        element: <Pricing />,
      },
      {
        path: "/contact",
        element: <Contact />,
      },
      {
        path: "/analysis",
        element: <Analysis />,
      },
      {
        path: "/scale",
        element: <Scale />,
      },
      {
        path: "/developer",
        element: <Developer />,
      },
      {
        path: "/privacy-policy",
        element: <Privacy />,
      },
      {
        path: "/terms",
        element: <Terms />,
      },
      {
        path: "/about",
        element: <About />,
      },
    ],
  },
]);
