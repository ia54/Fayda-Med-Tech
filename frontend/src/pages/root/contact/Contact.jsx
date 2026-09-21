import AboutContact from "../../../components/root/contact/AboutContact";
import ContactHero from "../../../components/root/contact/ContactHero";
import Testmonials from "../../../components/root/home/Testmonials";
import Faq from "../../../components/root/solutions/Faq";
import Payments from "../../../components/shared/payments/Payments";

const Contact = () => {
  return (
    <div>
      <ContactHero />
      {/* <ContactFrameTitle /> */}
      <AboutContact />
      <Faq />
      <Testmonials />
      <Payments />
    </div>
  );
};

export default Contact;
