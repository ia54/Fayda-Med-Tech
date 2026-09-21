import React from "react";
import Banner from "../../../components/shared/banner/Banner";

const Terms = () => {
  return (
    <div>
      <Banner
        bannerImage="/images/about/aboutBanner.png"
        subtitle="Terms of use and service"
        Fayda="Fayda"
        med="med"
        title="Terms & Conditions"
        description="Please review the following terms governing your use of our services."
        buttonText="Request a demo"
      />

      <section className="sectionGap mt-[60px] max-w-3xl">
        <h2 className="text-2xl md:text-3xl font-trap text-[#103E46] mb-4">Acceptance of Terms</h2>
        <p className="para text-[#103E46] mb-4">
          By accessing or using the platform, you agree to these Terms and our Privacy Policy.
        </p>

        <h3 className="text-xl md:text-2xl font-trap text-[#103E46] mt-8 mb-3">Use of Service</h3>
        <ul className="list-disc pl-6 space-y-2 text-[#103E46]">
          <li>Do not misuse, reverse engineer, or abuse the service.</li>
          <li>Comply with applicable laws and regulations.</li>
          <li>Maintain the confidentiality of your account credentials.</li>
        </ul>

        <h3 className="text-xl md:text-2xl font-trap text-[#103E46] mt-8 mb-3">Limitation of Liability</h3>
        <p className="para text-[#103E46]">
          To the maximum extent permitted by law, we are not liable for indirect, incidental, or consequential damages.
        </p>
      </section>
    </div>
  );
};

export default Terms;

