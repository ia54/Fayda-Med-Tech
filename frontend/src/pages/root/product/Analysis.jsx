import React from "react";
import Banner from "../../../components/shared/banner/Banner";
import Payments from "../../../components/shared/payments/Payments";

const Analysis = () => {
  return (
    <div>
      <Banner
        bannerImage="/images/solutions/solutionBanner.png"
        subtitle="Deep insights to optimize your PI billing"
        Fayda="Fayda"
        med="med"
        title="Analysis"
        description="Metrics that matter: denials, cycle times, collections, and forecasting—built for providers and law partners."
        buttonText="Request a demo"
      />

      <section className="sectionGap mt-[60px]">
        <p className="para max-w-3xl text-[#103E46]">
          Turn data into action. Track end-to-end claim performance with clean, actionable dashboards and exportable reports.
        </p>
      </section>

      <Payments />
    </div>
  );
};

export default Analysis;

