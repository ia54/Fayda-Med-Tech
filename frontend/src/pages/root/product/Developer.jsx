import React from "react";
import Banner from "../../../components/shared/banner/Banner";
import Payments from "../../../components/shared/payments/Payments";

const Developer = () => {
  return (
    <div>
      <Banner
        bannerImage="/images/solutions/solutionBanner.png"
        subtitle="Tools for developers and integrations"
        Fayda="Fayda"
        med="med"
        title="Developer"
        description="Integrate with your CRMs and systems. APIs and webhooks designed for PI workflows."
        buttonText="Request a demo"
      />

      <section className="sectionGap mt-[60px]">
        <p className="para max-w-3xl text-[#103E46]">
          Build on top of FAYDA MED with modern, secure endpoints. Contact us for sandbox access and documentation.
        </p>
      </section>

      <Payments />
    </div>
  );
};

export default Developer;

