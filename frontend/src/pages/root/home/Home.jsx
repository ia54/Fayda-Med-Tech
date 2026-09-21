import Different from "../../../components/root/home/Different";
import HomeAboutUs from "../../../components/root/home/HomeAboutUs";
import HomeHero from "../../../components/root/home/HomeHero";
import Integret from "../../../components/root/home/Integret";
import OurSolution from "../../../components/root/home/OurSolution";
import SpinningReveal from "../../../components/root/home/SpinningReveal";
import Testimonials from "../../../components/root/home/Testmonials";
import WhyNow from "../../../components/root/home/WhyNow";
import Payments from "../../../components/shared/payments/Payments";

const Home = () => {
  return (
    <div>
      <HomeHero />
      {/* <AboutFrameTitle /> */}
      <HomeAboutUs />
      <WhyNow />
      <OurSolution />
      <Different />
      <Integret />
      <SpinningReveal />
      <Testimonials />
      <Payments />
    </div>
  );
};

export default Home;
